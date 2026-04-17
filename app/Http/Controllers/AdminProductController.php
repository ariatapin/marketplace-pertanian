<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AdminProductController extends Controller
{
    use ApiResponse;

    private function responseSuccess(Request $request, string $message, array $payload = [], int $status = 200)
    {
        if ($request->expectsJson()) {
            return $this->apiSuccess($payload, $message, $status);
        }

        return back()->with('status', $message);
    }

    private function responseError(Request $request, string $message, array $errors = [], int $status = 422)
    {
        if ($request->expectsJson()) {
            return $this->apiError($message, $errors, $status);
        }

        return back()
            ->withErrors($errors ?: ['admin_product' => $message])
            ->withInput();
    }

    private function validatePayload(Request $request): array
    {
        if (! Schema::hasTable('warehouses')) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Modul gudang belum tersedia. Jalankan migration terbaru.',
            ]);
        }

        $activeWarehouseCount = (int) DB::table('warehouses')
            ->where('is_active', true)
            ->count();

        if ($activeWarehouseCount <= 0) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Belum ada gudang aktif. Tambahkan gudang dulu di menu Gudang.',
            ]);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'unit' => 'required|string|max:20',
            'min_order_qty' => 'required|integer|min:1',
            'stock_qty' => 'required|integer|min:0',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'is_active' => 'required|boolean',
        ]);

        $data['name'] = trim((string) $data['name']);
        $data['description'] = trim((string) ($data['description'] ?? '')) ?: null;
        $data['unit'] = strtolower(trim((string) ($data['unit'] ?? 'kg')));
        if ($data['unit'] === '') {
            $data['unit'] = 'kg';
        }

        $warehouse = DB::table('warehouses')
            ->where('id', (int) $data['warehouse_id'])
            ->first(['id', 'is_active']);

        if (! $warehouse || ! (bool) $warehouse->is_active) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Gudang yang dipilih tidak aktif. Pilih gudang aktif.',
            ]);
        }

        return $data;
    }

    public function store(Request $request)
    {
        $this->authorize('access-admin');

        $data = $this->validatePayload($request);

        $id = DB::table('admin_products')->insertGetId(array_merge($data, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        return $this->responseSuccess(
            $request,
            "Produk pengadaan admin #{$id} berhasil ditambahkan.",
            ['admin_product_id' => $id],
            201
        );
    }

    public function update(Request $request, int $adminProductId)
    {
        $this->authorize('access-admin');

        $product = DB::table('admin_products')
            ->where('id', $adminProductId)
            ->first(['id']);

        if (! $product) {
            return $this->responseError(
                $request,
                'Produk pengadaan tidak ditemukan.',
                ['admin_product' => 'Produk pengadaan tidak ditemukan.'],
                404
            );
        }

        $data = $this->validatePayload($request);

        DB::table('admin_products')
            ->where('id', $adminProductId)
            ->update(array_merge($data, [
                'updated_at' => now(),
            ]));

        return $this->responseSuccess(
            $request,
            "Produk pengadaan admin #{$adminProductId} berhasil diperbarui.",
            ['admin_product_id' => $adminProductId]
        );
    }

    public function destroy(Request $request, int $adminProductId)
    {
        $this->authorize('access-admin');

        $product = DB::table('admin_products')
            ->where('id', $adminProductId)
            ->first(['id', 'name']);

        if (! $product) {
            return $this->responseError(
                $request,
                'Produk pengadaan tidak ditemukan.',
                ['admin_product' => 'Produk pengadaan tidak ditemukan.'],
                404
            );
        }

        $hasOrderHistory = Schema::hasTable('admin_order_items')
            && DB::table('admin_order_items')
                ->where('admin_product_id', $adminProductId)
                ->exists();

        if ($hasOrderHistory) {
            DB::table('admin_products')
                ->where('id', $adminProductId)
                ->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);

            return $this->responseSuccess(
                $request,
                "Produk {$product->name} sudah punya riwayat order, jadi dinonaktifkan (tidak dihapus).",
                [
                    'admin_product_id' => $adminProductId,
                    'soft_action' => 'deactivated_due_to_history',
                ]
            );
        }

        DB::table('admin_products')
            ->where('id', $adminProductId)
            ->delete();

        return $this->responseSuccess(
            $request,
            "Produk pengadaan admin #{$adminProductId} berhasil dihapus.",
            ['admin_product_id' => $adminProductId]
        );
    }
}
