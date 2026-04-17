<?php

namespace App\Http\Controllers\Api\Mitra;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\StoreProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MitraProductApiController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $mitra = $request->user();

        $rows = StoreProduct::query()
            ->where('mitra_id', $mitra->id)
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'description', 'price', 'stock_qty', 'image_url', 'updated_at']);

        return $this->apiSuccess($rows, 'Daftar produk mitra berhasil diambil.');
    }

    public function adjustStock(Request $request, StoreProduct $product)
    {
        $this->authorize('update', $product);

        $data = $request->validate([
            'delta' => 'required|integer|not_in:0|min:-100000|max:100000',
            'note' => 'nullable|string|max:255',
        ]);

        $beforeStock = (int) $product->stock_qty;
        $delta = (int) $data['delta'];
        $newStock = $beforeStock + $delta;
        if ($newStock < 0) {
            throw ValidationException::withMessages([
                'delta' => 'Penyesuaian stok membuat stok menjadi minus.',
            ]);
        }

        $product->update(['stock_qty' => $newStock]);
        $note = $data['note'] ?? null;
        $this->logStockMutation(
            $product->fresh(),
            $beforeStock,
            $delta,
            'adjust',
            $note ?: 'Penyesuaian stok dari endpoint API mitra'
        );

        return $this->apiSuccess([
            'product_id' => $product->id,
            'stock_qty' => $newStock,
        ], 'Stok produk mitra berhasil diperbarui.');
    }

    public function mutations(StoreProduct $product)
    {
        $this->authorize('view', $product);

        $rows = collect();
        if (Schema::hasTable('store_product_stock_mutations')) {
            $rows = DB::table('store_product_stock_mutations')
                ->where('store_product_id', $product->id)
                ->orderByDesc('id')
                ->get(['id', 'change_type', 'qty_before', 'qty_delta', 'qty_after', 'note', 'created_at']);
        }

        return $this->apiSuccess($rows, 'Riwayat mutasi stok produk berhasil diambil.');
    }

    private function logStockMutation(StoreProduct $product, int $qtyBefore, int $qtyDelta, string $changeType, ?string $note = null): void
    {
        if (! Schema::hasTable('store_product_stock_mutations')) {
            return;
        }

        DB::table('store_product_stock_mutations')->insert([
            'mitra_id' => (int) $product->mitra_id,
            'store_product_id' => (int) $product->id,
            'product_name' => (string) $product->name,
            'change_type' => $changeType,
            'qty_before' => $qtyBefore,
            'qty_delta' => $qtyDelta,
            'qty_after' => $qtyBefore + $qtyDelta,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
