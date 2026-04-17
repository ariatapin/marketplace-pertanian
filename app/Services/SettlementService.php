<?php

namespace App\Services;

use App\Support\Concerns\HandlesWalletLedgerMutation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettlementService
{
    use HandlesWalletLedgerMutation;

    public function settleIfEligible(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            if (! $order) {
                throw ValidationException::withMessages(['order' => 'Order tidak ditemukan']);
            }

            // Komisi affiliate hanya dari order marketplace Mitra (store_online).
            if ((string) ($order->order_source ?? '') !== 'store_online') {
                return;
            }

            // Hanya jika completed & paid
            if ($order->order_status !== 'completed' || $order->payment_status !== 'paid') {
                return;
            }

            $admin = User::query()
                ->whereNormalizedRole('admin')
                ->orderBy('id')
                ->first(['id']);
            if (! $admin) {
                throw ValidationException::withMessages(['admin' => 'Admin user tidak ditemukan']);
            }

            $adminProfile = DB::table('admin_profiles')->where('user_id', $admin->id)->first();
            $platformFeePercent = $adminProfile?->platform_fee_percent ?? 0;
            $gross = round((float) $order->total_amount, 2);

            // Commission snapshot dibaca dari order item valid.
            // Validasi mode affiliate dilakukan saat attribution checkout agar settlement tidak
            // bergantung pada perubahan mode setelah order dibuat.
            $affGroups = $this->eligibleAffiliateGroups($orderId);
            $affiliateCommission = round((float) $affGroups->sum(fn ($group) => (float) ($group->total ?? 0)), 2);

            $platformFee = round($gross * ((float) $platformFeePercent / 100), 2);
            $netToSeller = round(max(0, $gross - $platformFee - $affiliateCommission), 2);

            DB::table('order_settlements')->insertOrIgnore([
                'order_id' => $orderId,
                'seller_id' => $order->seller_id,
                'buyer_id' => $order->buyer_id,
                'gross_amount' => $gross,
                'platform_fee' => $platformFee,
                'affiliate_commission' => $affiliateCommission,
                'net_to_seller' => $netToSeller,
                'status' => 'paid',
                'eligible_at' => now(),
                'settled_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $settlement = DB::table('order_settlements')
                ->where('order_id', $orderId)
                ->lockForUpdate()
                ->first([
                    'order_id',
                    'seller_id',
                    'gross_amount',
                    'platform_fee',
                    'affiliate_commission',
                    'net_to_seller',
                ]);

            if (! $settlement) {
                throw ValidationException::withMessages([
                    'settlement' => 'Gagal memuat snapshot settlement order.',
                ]);
            }

            $walletActorIds = collect([(int) $admin->id, (int) $settlement->seller_id])
                ->merge($affGroups->pluck('affiliate_id')->map(fn ($id) => (int) $id))
                ->unique()
                ->values()
                ->all();
            $this->lockUsersForWalletMutation($walletActorIds);

            // Wallet transactions:
            // 1) Admin menerima gross (escrow masuk)
            $this->insertWalletTransaction(
                walletId: (int) $admin->id,
                amount: (float) $settlement->gross_amount,
                transactionType: 'escrow_in',
                referenceOrderId: $orderId,
                description: 'Escrow masuk dari order',
                idempotencyKey: "settlement:order:{$orderId}:wallet:{$admin->id}:escrow_in"
            );

            // 2) Seller mendapatkan net
            $this->insertWalletTransaction(
                walletId: (int) $settlement->seller_id,
                amount: (float) $settlement->net_to_seller,
                transactionType: 'sale_revenue',
                referenceOrderId: $orderId,
                description: 'Pendapatan penjual dari order',
                idempotencyKey: "settlement:order:{$orderId}:wallet:{$settlement->seller_id}:sale_revenue"
            );

            // 3) Affiliate (jika ada), agregat per affiliate_id
            foreach ($affGroups as $group) {
                if ((float) $group->total <= 0) {
                    continue;
                }

                $this->insertWalletTransaction(
                    walletId: (int) $group->affiliate_id,
                    amount: round((float) $group->total, 2),
                    transactionType: 'affiliate_commission',
                    referenceOrderId: $orderId,
                    description: 'Komisi affiliate dari order',
                    idempotencyKey: "settlement:order:{$orderId}:wallet:{$group->affiliate_id}:affiliate_commission"
                );
            }
        });
    }

    private function eligibleAffiliateGroups(int $orderId)
    {
        $groups = DB::table('order_items')
            ->select('affiliate_id', DB::raw('SUM(commission_amount) as total'))
            ->where('order_id', $orderId)
            ->whereNotNull('affiliate_id')
            ->where('commission_amount', '>', 0)
            ->groupBy('affiliate_id')
            ->get();

        return $groups
            ->filter(function ($group): bool {
                $affiliateId = (int) ($group->affiliate_id ?? 0);
                $total = (float) ($group->total ?? 0);

                if ($affiliateId <= 0 || $total <= 0) {
                    return false;
                }

                return $this->isEligibleAffiliateRecipient($affiliateId);
            })
            ->values();
    }

    private function isEligibleAffiliateRecipient(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $user = DB::table('users')
            ->where('id', $userId)
            ->first(['id', 'role']);

        if (! $user) {
            return false;
        }

        $role = strtolower(trim((string) ($user->role ?? '')));
        return $role === 'consumer';
    }

    private function insertWalletTransaction(
        int $walletId,
        float $amount,
        string $transactionType,
        ?int $referenceOrderId,
        string $description,
        string $idempotencyKey
    ): void {
        $normalizedAmount = round($amount, 2);

        $this->ensureWalletLedgerMutation([
            'wallet_id' => $walletId,
            'amount' => $normalizedAmount,
            'transaction_type' => $transactionType,
            'idempotency_key' => $idempotencyKey,
            'reference_order_id' => $referenceOrderId,
            'reference_withdraw_id' => null,
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'Ledger wallet tidak konsisten saat settlement order #' . $referenceOrderId . '.');
    }
}
