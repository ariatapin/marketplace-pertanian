<?php

namespace App\Services\Procurement;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProcurementOrderCancellationService
{
    public function assertCancellablePaymentState(object $order, bool $hasPaymentColumns): void
    {
        if (! $hasPaymentColumns) {
            return;
        }

        $paymentStatus = strtolower(trim((string) ($order->payment_status ?? 'unpaid')));
        if ($paymentStatus === 'paid') {
            throw ValidationException::withMessages([
                'status' => 'Order dengan pembayaran paid tidak dapat dibatalkan langsung. Gunakan alur refund terlebih dahulu.',
            ]);
        }

        if ($paymentStatus === 'pending_verification') {
            throw ValidationException::withMessages([
                'status' => 'Order dengan pembayaran pending_verification tidak dapat dibatalkan. Selesaikan verifikasi pembayaran terlebih dahulu.',
            ]);
        }
    }

    /**
     * Harus dipanggil di dalam DB transaction aktif.
     *
     * @return array{restored_lines:int,restored_qty:int}
     */
    public function restoreReservedStock(int $adminOrderId): array
    {
        if (! Schema::hasTable('admin_order_items') || ! Schema::hasTable('admin_products')) {
            return [
                'restored_lines' => 0,
                'restored_qty' => 0,
            ];
        }

        $items = DB::table('admin_order_items')
            ->where('admin_order_id', $adminOrderId)
            ->lockForUpdate()
            ->get(['admin_product_id', 'qty']);

        $restoredLines = 0;
        $restoredQty = 0;

        foreach ($items as $item) {
            $product = DB::table('admin_products')
                ->where('id', (int) $item->admin_product_id)
                ->lockForUpdate()
                ->first(['id', 'stock_qty']);

            if (! $product) {
                continue;
            }

            $qty = max(0, (int) $item->qty);

            DB::table('admin_products')
                ->where('id', (int) $product->id)
                ->update([
                    'stock_qty' => ((int) $product->stock_qty) + $qty,
                    'updated_at' => now(),
                ]);

            $restoredLines++;
            $restoredQty += $qty;
        }

        return [
            'restored_lines' => $restoredLines,
            'restored_qty' => $restoredQty,
        ];
    }
}
