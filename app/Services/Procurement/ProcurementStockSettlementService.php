<?php

namespace App\Services\Procurement;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProcurementStockSettlementService
{
    public function settleDeliveredOrder(
        int $adminOrderId,
        ?int $actorUserId = null,
        ?string $actorRole = null
    ): array {
        $order = DB::table('admin_orders')
            ->where('id', $adminOrderId)
            ->lockForUpdate()
            ->first(['id', 'mitra_id', 'status']);

        if (! $order) {
            throw ValidationException::withMessages(['order' => 'Admin order tidak ditemukan.']);
        }

        if ((string) $order->status !== 'delivered') {
            throw ValidationException::withMessages([
                'status' => 'Settlement stok hanya dapat dijalankan saat status order delivered.',
            ]);
        }

        $settlementInserted = DB::table('procurement_stock_settlements')->insertOrIgnore([
            'admin_order_id' => (int) $order->id,
            'mitra_id' => (int) $order->mitra_id,
            'settled_by_user_id' => $actorUserId,
            'settled_by_role' => $actorRole,
            'line_count' => 0,
            'total_qty' => 0,
            'settled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ((int) $settlementInserted === 0) {
            return [
                'settled' => false,
                'already_settled' => true,
                'line_count' => 0,
                'total_qty' => 0,
            ];
        }

        $items = DB::table('admin_order_items')
            ->leftJoin('admin_products', 'admin_products.id', '=', 'admin_order_items.admin_product_id')
            ->where('admin_order_items.admin_order_id', $adminOrderId)
            ->orderBy('admin_order_items.id')
            ->get([
                'admin_order_items.id',
                'admin_order_items.admin_product_id',
                'admin_order_items.product_name',
                'admin_order_items.price_per_unit',
                'admin_order_items.unit as order_item_unit',
                'admin_order_items.qty',
                'admin_products.description as admin_product_description',
                'admin_products.unit as admin_product_unit',
            ]);

        $lineCount = 0;
        $totalQty = 0;
        foreach ($items as $item) {
            $lineCount++;
            $totalQty += (int) $item->qty;
            $this->receiveLine((int) $order->mitra_id, $adminOrderId, $item);
        }

        DB::table('procurement_stock_settlements')
            ->where('admin_order_id', $adminOrderId)
            ->update([
                'line_count' => $lineCount,
                'total_qty' => $totalQty,
                'updated_at' => now(),
            ]);

        return [
            'settled' => true,
            'already_settled' => false,
            'line_count' => $lineCount,
            'total_qty' => $totalQty,
        ];
    }

    private function receiveLine(int $mitraId, int $adminOrderId, object $item): void
    {
        $hasSourceProductColumn = Schema::hasColumn('store_products', 'source_admin_product_id');
        $hasActiveColumn = Schema::hasColumn('store_products', 'is_active');
        $hasUnitColumn = Schema::hasColumn('store_products', 'unit');

        $resolvedUnit = strtolower(trim((string) ($item->order_item_unit ?? $item->admin_product_unit ?? 'kg')));
        if ($resolvedUnit === 'lt') {
            $resolvedUnit = 'liter';
        }
        if ($resolvedUnit === '') {
            $resolvedUnit = 'kg';
        }

        $storeProductQuery = DB::table('store_products')->where('mitra_id', $mitraId);
        if ($hasSourceProductColumn) {
            $storeProductQuery->where('source_admin_product_id', (int) $item->admin_product_id);
        } else {
            $storeProductQuery->where('name', (string) $item->product_name);
        }

        $selectColumns = ['id', 'name', 'stock_qty'];
        if ($hasUnitColumn) {
            $selectColumns[] = 'unit';
        }

        $storeProduct = $storeProductQuery
            ->lockForUpdate()
            ->first($selectColumns);

        if (! $storeProduct) {
            $insertPayload = [
                'mitra_id' => $mitraId,
                'name' => (string) $item->product_name,
                'description' => $item->admin_product_description ?: null,
                'price' => (float) $item->price_per_unit,
                'stock_qty' => (int) $item->qty,
                'image_url' => null,
                'is_affiliate_enabled' => false,
                'affiliate_commission' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($hasSourceProductColumn) {
                $insertPayload['source_admin_product_id'] = (int) $item->admin_product_id;
            }

            if ($hasActiveColumn) {
                // Produk hasil pengadaan admin dibuat nonaktif terlebih dahulu.
                $insertPayload['is_active'] = false;
            }

            if ($hasUnitColumn) {
                $insertPayload['unit'] = $resolvedUnit;
            }

            $storeProductId = DB::table('store_products')->insertGetId($insertPayload);
            $this->logStockMutation(
                mitraId: $mitraId,
                storeProductId: $storeProductId,
                productName: (string) $item->product_name,
                qtyBefore: 0,
                qtyDelta: (int) $item->qty,
                note: "Stok masuk dari settlement pengadaan #{$adminOrderId}"
            );

            return;
        }

        $qtyBefore = (int) $storeProduct->stock_qty;
        $qtyDelta = (int) $item->qty;
        $updatePayload = [
            'stock_qty' => $qtyBefore + $qtyDelta,
            'updated_at' => now(),
        ];
        if ($hasUnitColumn && trim((string) ($storeProduct->unit ?? '')) === '') {
            $updatePayload['unit'] = $resolvedUnit;
        }

        DB::table('store_products')
            ->where('id', $storeProduct->id)
            ->update($updatePayload);

        $this->logStockMutation(
            mitraId: $mitraId,
            storeProductId: (int) $storeProduct->id,
            productName: (string) $storeProduct->name,
            qtyBefore: $qtyBefore,
            qtyDelta: $qtyDelta,
            note: "Stok masuk dari settlement pengadaan #{$adminOrderId}"
        );
    }

    private function logStockMutation(
        int $mitraId,
        int $storeProductId,
        string $productName,
        int $qtyBefore,
        int $qtyDelta,
        string $note
    ): void {
        if (! Schema::hasTable('store_product_stock_mutations')) {
            return;
        }

        DB::table('store_product_stock_mutations')->insert([
            'mitra_id' => $mitraId,
            'store_product_id' => $storeProductId,
            'product_name' => $productName,
            'change_type' => 'procurement_receive',
            'qty_before' => $qtyBefore,
            'qty_delta' => $qtyDelta,
            'qty_after' => $qtyBefore + $qtyDelta,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
