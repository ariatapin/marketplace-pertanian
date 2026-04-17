<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisputeService
{
    public function __construct(
        protected RefundService $refundService
    ) {}

    /**
     * @param  array{category:string,description:string,evidence_urls?:array<int,string>|null}  $payload
     * @return array{dispute_id:int,status:string}
     */
    public function openByBuyer(User $buyer, int $orderId, array $payload): array
    {
        return DB::transaction(function () use ($buyer, $orderId, $payload) {
            $order = DB::table('orders')
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first([
                    'id',
                    'buyer_id',
                    'seller_id',
                    'order_status',
                    'payment_status',
                ]);
            if (! $order) {
                throw ValidationException::withMessages(['order' => 'Order tidak ditemukan.']);
            }

            if ((int) $order->buyer_id !== (int) $buyer->id) {
                throw ValidationException::withMessages(['order' => 'Order bukan milik buyer login.']);
            }

            $orderStatus = strtolower(trim((string) ($order->order_status ?? '')));
            $paymentStatus = strtolower(trim((string) ($order->payment_status ?? '')));
            if (! in_array($paymentStatus, ['paid'], true)) {
                throw ValidationException::withMessages([
                    'payment_status' => 'Sengketa hanya dapat dibuat untuk order yang sudah dibayar.',
                ]);
            }

            if (! in_array($orderStatus, ['paid', 'packed', 'shipped', 'completed'], true)) {
                throw ValidationException::withMessages([
                    'order_status' => 'Status order belum memenuhi syarat untuk membuka sengketa.',
                ]);
            }

            $existingDispute = DB::table('disputes')
                ->where('order_id', $orderId)
                ->lockForUpdate()
                ->first(['id', 'status']);

            if ($existingDispute) {
                throw ValidationException::withMessages([
                    'dispute' => 'Sengketa untuk order ini sudah pernah dibuat.',
                ]);
            }

            $evidenceUrls = $this->normalizeEvidenceUrls($payload['evidence_urls'] ?? []);

            $disputeId = DB::table('disputes')->insertGetId([
                'order_id' => $orderId,
                'buyer_id' => (int) $order->buyer_id,
                'seller_id' => (int) $order->seller_id,
                'opened_by' => (int) $buyer->id,
                'category' => strtolower(trim((string) ($payload['category'] ?? 'other'))),
                'description' => trim((string) ($payload['description'] ?? '')),
                'status' => 'pending',
                'handled_by' => null,
                'handled_at' => null,
                'resolution' => null,
                'resolution_notes' => null,
                'evidence_urls' => json_encode($evidenceUrls, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'dispute_id' => (int) $disputeId,
                'status' => 'pending',
            ];
        });
    }

    /**
     * @param  array{status:string,resolution?:string|null,refund_amount?:float|int|string|null,resolution_notes?:string|null}  $payload
     * @return array{dispute_id:int,status:string,refund_id:int|null}
     */
    public function reviewByAdmin(User $admin, int $disputeId, array $payload): array
    {
        return DB::transaction(function () use ($admin, $disputeId, $payload) {
            $dispute = DB::table('disputes')
                ->where('id', $disputeId)
                ->lockForUpdate()
                ->first();
            if (! $dispute) {
                throw ValidationException::withMessages(['dispute' => 'Dispute tidak ditemukan.']);
            }

            $order = DB::table('orders')
                ->where('id', (int) $dispute->order_id)
                ->lockForUpdate()
                ->first(['id', 'buyer_id', 'seller_id', 'total_amount']);
            if (! $order) {
                throw ValidationException::withMessages(['order' => 'Order sengketa tidak ditemukan.']);
            }

            $currentStatus = strtolower(trim((string) ($dispute->status ?? 'pending')));
            $targetStatus = strtolower(trim((string) ($payload['status'] ?? '')));

            if (in_array($currentStatus, ['resolved_buyer', 'resolved_seller', 'cancelled'], true)) {
                if ($targetStatus === $currentStatus) {
                    $existingRefundId = (int) (DB::table('refunds')
                        ->where('order_id', (int) $order->id)
                        ->value('id') ?? 0);

                    return [
                        'dispute_id' => (int) $disputeId,
                        'status' => $currentStatus,
                        'refund_id' => $existingRefundId > 0 ? $existingRefundId : null,
                    ];
                }

                throw ValidationException::withMessages([
                    'status' => 'Dispute sudah final dan tidak dapat dipindahkan ke status lain.',
                ]);
            }

            $this->assertReviewTransition($currentStatus, $targetStatus);

            $resolution = trim((string) ($payload['resolution'] ?? ''));
            $resolution = $resolution !== '' ? strtolower($resolution) : null;
            $resolutionNotes = trim((string) ($payload['resolution_notes'] ?? ''));
            $resolutionNotes = $resolutionNotes !== '' ? $resolutionNotes : null;

            $refundId = null;
            if ($targetStatus === 'resolved_buyer') {
                if (! in_array($resolution, ['refund_full', 'refund_partial'], true)) {
                    throw ValidationException::withMessages([
                        'resolution' => 'Resolusi sengketa buyer harus refund_full atau refund_partial.',
                    ]);
                }

                $refundAmount = $resolution === 'refund_full'
                    ? round((float) $order->total_amount, 2)
                    : round((float) ($payload['refund_amount'] ?? 0), 2);
                if ($resolution === 'refund_partial' && $refundAmount <= 0) {
                    throw ValidationException::withMessages([
                        'refund_amount' => 'Nominal refund partial harus lebih besar dari 0.',
                    ]);
                }

                $refund = $this->refundService->upsertApprovedFromDispute(
                    dispute: $dispute,
                    order: $order,
                    adminUserId: (int) $admin->id,
                    amount: $refundAmount,
                    resolution: $resolution,
                    resolutionNotes: $resolutionNotes
                );
                $refundId = (int) ($refund['refund_id'] ?? 0) ?: null;
            }

            if (in_array($targetStatus, ['resolved_seller', 'cancelled'], true)) {
                DB::table('refunds')
                    ->where('order_id', (int) $order->id)
                    ->whereIn('status', ['pending', 'approved'])
                    ->update([
                        'status' => 'cancelled',
                        'processed_by' => (int) $admin->id,
                        'processed_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            if ($targetStatus === 'resolved_seller' && $resolution === null) {
                $resolution = 'release_to_seller';
            }

            DB::table('disputes')
                ->where('id', $disputeId)
                ->update([
                    'status' => $targetStatus,
                    'handled_by' => (int) $admin->id,
                    'handled_at' => now(),
                    'resolution' => $resolution,
                    'resolution_notes' => $resolutionNotes,
                    'updated_at' => now(),
                ]);

            return [
                'dispute_id' => (int) $disputeId,
                'status' => $targetStatus,
                'refund_id' => $refundId,
            ];
        });
    }

    /**
     * @param  array<int, mixed>  $rawEvidenceUrls
     * @return array<int, string>
     */
    private function normalizeEvidenceUrls(array $rawEvidenceUrls): array
    {
        return collect($rawEvidenceUrls)
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function assertReviewTransition(string $fromStatus, string $toStatus): void
    {
        $allowedTargets = match ($fromStatus) {
            'pending' => ['pending', 'under_review', 'resolved_buyer', 'resolved_seller', 'cancelled'],
            'under_review' => ['under_review', 'resolved_buyer', 'resolved_seller', 'cancelled'],
            default => [$fromStatus],
        };

        if (in_array($toStatus, $allowedTargets, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => 'Transisi status dispute tidak valid.',
        ]);
    }
}
