<?php

namespace App\Services;

use App\Support\Concerns\HandlesWalletLedgerMutation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RefundService
{
    use HandlesWalletLedgerMutation;

    /**
     * @return array{refund_id:int,status:string,amount:float}
     */
    public function upsertApprovedFromDispute(
        object $dispute,
        object $order,
        int $adminUserId,
        float $amount,
        string $resolution,
        ?string $resolutionNotes = null
    ): array {
        $safeAmount = round($amount, 2);
        if ($safeAmount <= 0) {
            throw ValidationException::withMessages([
                'refund_amount' => 'Nominal refund harus lebih besar dari 0.',
            ]);
        }

        $orderTotal = round((float) ($order->total_amount ?? 0), 2);
        if ($orderTotal <= 0 || $safeAmount > $orderTotal) {
            throw ValidationException::withMessages([
                'refund_amount' => 'Nominal refund tidak boleh melebihi total order.',
            ]);
        }

        $existing = DB::table('refunds')
            ->where('order_id', (int) $order->id)
            ->lockForUpdate()
            ->first(['id', 'status']);

        if ($existing && (string) $existing->status === 'paid') {
            throw ValidationException::withMessages([
                'refund' => 'Refund untuk order ini sudah berstatus paid dan tidak dapat diubah.',
            ]);
        }

        $reason = $resolution === 'refund_full'
            ? "Refund penuh dari dispute #{$dispute->id}"
            : "Refund sebagian dari dispute #{$dispute->id}";

        $payload = [
            'buyer_id' => (int) $order->buyer_id,
            'seller_id' => (int) $order->seller_id,
            'amount' => $safeAmount,
            'reason' => $reason,
            'status' => 'approved',
            'processed_by' => $adminUserId,
            'processed_at' => now(),
            'refund_proof_url' => null,
            'refund_reference' => null,
            'notes' => $resolutionNotes !== null && trim($resolutionNotes) !== '' ? trim($resolutionNotes) : null,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('refunds')
                ->where('id', (int) $existing->id)
                ->update($payload);

            return [
                'refund_id' => (int) $existing->id,
                'status' => 'approved',
                'amount' => $safeAmount,
            ];
        }

        $refundId = DB::table('refunds')->insertGetId($payload + [
            'order_id' => (int) $order->id,
            'created_at' => now(),
        ]);

        return [
            'refund_id' => (int) $refundId,
            'status' => 'approved',
            'amount' => $safeAmount,
        ];
    }

    /**
     * @return array{refund_id:int,status:string,wallet_refund:bool}
     */
    public function markPaid(
        int $refundId,
        int $adminUserId,
        ?string $transferReference = null,
        ?string $refundProofUrl = null,
        ?string $notes = null
    ): array {
        return DB::transaction(function () use ($refundId, $adminUserId, $transferReference, $refundProofUrl, $notes) {
            $refund = DB::table('refunds')
                ->where('id', $refundId)
                ->lockForUpdate()
                ->first();
            if (! $refund) {
                throw ValidationException::withMessages(['refund' => 'Refund tidak ditemukan.']);
            }

            if ((string) $refund->status === 'paid') {
                $order = DB::table('orders')
                    ->where('id', (int) $refund->order_id)
                    ->first(['id', 'buyer_id']);
                $walletRefundApplied = false;
                if ($order) {
                    $walletRefundApplied = DB::table('wallet_transactions')
                        ->where('wallet_id', (int) $order->buyer_id)
                        ->where('reference_order_id', (int) $order->id)
                        ->where('transaction_type', 'order_refund_wallet')
                        ->exists();
                }

                return [
                    'refund_id' => (int) $refundId,
                    'status' => 'paid',
                    'wallet_refund' => $walletRefundApplied,
                ];
            }

            if (! in_array((string) $refund->status, ['approved', 'pending'], true)) {
                throw ValidationException::withMessages(['status' => 'Refund harus berstatus approved/pending sebelum ditandai paid.']);
            }

            $order = DB::table('orders')
                ->where('id', (int) $refund->order_id)
                ->lockForUpdate()
                ->first(['id', 'buyer_id', 'seller_id', 'total_amount', 'payment_status']);
            if (! $order) {
                throw ValidationException::withMessages(['order' => 'Order untuk refund tidak ditemukan.']);
            }

            $amount = round((float) $refund->amount, 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Nominal refund tidak valid.']);
            }

            $needsWalletRefund = $this->shouldCreditWalletBuyer((int) $order->id, (int) $order->buyer_id);
            if (! $this->hasWalletLedgerColumns()) {
                throw ValidationException::withMessages([
                    'wallet' => 'Ledger wallet belum siap untuk memproses refund.',
                ]);
            }

            $orderTotal = round((float) ($order->total_amount ?? 0), 2);
            $reversalPlan = $this->buildRevenueReversalPlan(
                orderId: (int) $order->id,
                sellerId: (int) $order->seller_id,
                refundAmount: $amount,
                orderTotal: $orderTotal
            );

            $walletRefundApplied = false;
            $lockIds = collect([(int) $order->buyer_id, (int) $adminUserId, (int) $order->seller_id])
                ->merge(collect($reversalPlan['affiliate_rows'])->pluck('wallet_id'))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->sort()
                ->values()
                ->all();
            $this->lockUsersForWalletMutation($lockIds);

            if ($needsWalletRefund) {
                $adminBalance = (float) DB::table('wallet_transactions')
                    ->where('wallet_id', (int) $adminUserId)
                    ->sum('amount');
                if ($adminBalance < $amount) {
                    throw ValidationException::withMessages([
                        'admin_balance' => 'Saldo admin tidak cukup untuk memproses refund wallet.',
                    ]);
                }

                $this->ensureWalletLedgerMutation([
                    'wallet_id' => (int) $order->buyer_id,
                    'amount' => $amount,
                    'transaction_type' => 'order_refund_wallet',
                    'idempotency_key' => "refund:{$refundId}:wallet:{$order->buyer_id}:order_refund_wallet",
                    'reference_order_id' => (int) $order->id,
                    'reference_withdraw_id' => null,
                    'description' => "Refund order #{$order->id}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ], 'Ledger wallet tidak konsisten saat memproses refund.');

                $walletRefundApplied = true;
            }

            $sellerReversalAmount = round((float) ($reversalPlan['seller_amount'] ?? 0), 2);
            if ($sellerReversalAmount > 0) {
                $this->ensureWalletLedgerMutation([
                    'wallet_id' => (int) $order->seller_id,
                    'amount' => -1 * $sellerReversalAmount,
                    'transaction_type' => 'refund_sale_reversal',
                    'idempotency_key' => "refund:{$refundId}:wallet:{$order->seller_id}:refund_sale_reversal",
                    'reference_order_id' => (int) $order->id,
                    'reference_withdraw_id' => null,
                    'description' => "Reversal pendapatan seller untuk refund #{$refundId}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ], 'Ledger wallet tidak konsisten saat memproses refund.');
            }

            foreach ($reversalPlan['affiliate_rows'] as $affiliateRow) {
                $affiliateWalletId = (int) ($affiliateRow['wallet_id'] ?? 0);
                $affiliateAmount = round((float) ($affiliateRow['amount'] ?? 0), 2);

                if ($affiliateWalletId <= 0 || $affiliateAmount <= 0) {
                    continue;
                }

                $this->ensureWalletLedgerMutation([
                    'wallet_id' => $affiliateWalletId,
                    'amount' => -1 * $affiliateAmount,
                    'transaction_type' => 'refund_affiliate_reversal',
                    'idempotency_key' => "refund:{$refundId}:wallet:{$affiliateWalletId}:refund_affiliate_reversal",
                    'reference_order_id' => (int) $order->id,
                    'reference_withdraw_id' => null,
                    'description' => "Reversal komisi affiliate untuk refund #{$refundId}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ], 'Ledger wallet tidak konsisten saat memproses refund.');
            }

            $this->ensureWalletLedgerMutation([
                'wallet_id' => (int) $adminUserId,
                'amount' => -1 * $amount,
                'transaction_type' => 'refund_admin_payout',
                'idempotency_key' => "refund:{$refundId}:wallet:{$adminUserId}:refund_admin_payout",
                'reference_order_id' => (int) $order->id,
                'reference_withdraw_id' => null,
                'description' => "Pencairan refund #{$refundId} untuk order #{$order->id}",
                'created_at' => now(),
                'updated_at' => now(),
            ], 'Ledger wallet tidak konsisten saat memproses refund.');

            DB::table('refunds')
                ->where('id', $refundId)
                ->update([
                    'status' => 'paid',
                    'processed_by' => (int) $adminUserId,
                    'processed_at' => now(),
                    'refund_reference' => $transferReference !== null && trim($transferReference) !== '' ? trim($transferReference) : null,
                    'refund_proof_url' => $refundProofUrl !== null && trim($refundProofUrl) !== '' ? trim($refundProofUrl) : null,
                    'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : $refund->notes,
                    'updated_at' => now(),
                ]);

            DB::table('orders')
                ->where('id', (int) $order->id)
                ->update([
                    'payment_status' => 'refunded',
                    'updated_at' => now(),
                ]);

            if (Schema::hasTable('order_settlements')) {
                DB::table('order_settlements')
                    ->where('order_id', (int) $order->id)
                    ->update([
                        'status' => 'refunded',
                        'updated_at' => now(),
                    ]);
            }

            return [
                'refund_id' => (int) $refundId,
                'status' => 'paid',
                'wallet_refund' => $walletRefundApplied,
            ];
        });
    }

    /**
     * @return array{
     *     seller_amount:float,
     *     affiliate_rows:array<int, array{wallet_id:int,amount:float}>
     * }
     */
    private function buildRevenueReversalPlan(
        int $orderId,
        int $sellerId,
        float $refundAmount,
        float $orderTotal
    ): array {
        $normalizedRefund = round($refundAmount, 2);
        $normalizedTotal = round($orderTotal, 2);
        if ($orderId <= 0 || $normalizedRefund <= 0 || $normalizedTotal <= 0) {
            return [
                'seller_amount' => 0.0,
                'affiliate_rows' => [],
            ];
        }

        $ratio = min(1.0, max(0.0, $normalizedRefund / $normalizedTotal));
        if ($ratio <= 0) {
            return [
                'seller_amount' => 0.0,
                'affiliate_rows' => [],
            ];
        }

        $sellerCredited = round((float) DB::table('wallet_transactions')
            ->where('wallet_id', $sellerId)
            ->where('reference_order_id', $orderId)
            ->where('transaction_type', 'sale_revenue')
            ->where('amount', '>', 0)
            ->sum('amount'), 2);
        $sellerReversalAmount = round($sellerCredited * $ratio, 2);

        $affiliateCredits = DB::table('wallet_transactions')
            ->select('wallet_id', DB::raw('SUM(amount) as credited_total'))
            ->where('reference_order_id', $orderId)
            ->where('transaction_type', 'affiliate_commission')
            ->where('amount', '>', 0)
            ->groupBy('wallet_id')
            ->orderBy('wallet_id')
            ->get();

        $affiliateRows = $affiliateCredits
            ->map(function ($row) use ($ratio) {
                $walletId = (int) ($row->wallet_id ?? 0);
                $credited = round((float) ($row->credited_total ?? 0), 2);
                $reversal = round($credited * $ratio, 2);

                return [
                    'wallet_id' => $walletId,
                    'amount' => $reversal,
                ];
            })
            ->filter(fn (array $row) => $row['wallet_id'] > 0 && (float) $row['amount'] > 0)
            ->values()
            ->all();

        return [
            'seller_amount' => max(0.0, $sellerReversalAmount),
            'affiliate_rows' => $affiliateRows,
        ];
    }

    private function shouldCreditWalletBuyer(int $orderId, int $buyerId): bool
    {
        if (! Schema::hasTable('wallet_transactions') || ! Schema::hasColumn('wallet_transactions', 'reference_order_id')) {
            return false;
        }

        return DB::table('wallet_transactions')
            ->where('wallet_id', $buyerId)
            ->where('reference_order_id', $orderId)
            ->where('transaction_type', 'order_payment_wallet')
            ->where('amount', '<', 0)
            ->exists();
    }

}
