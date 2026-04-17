<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class WarehousePageController extends Controller
{
    public function __invoke(Request $request)
    {
        $keyword = trim($request->string('q')->toString());
        $status = trim($request->string('status')->toString());
        $provinceId = (int) $request->integer('province_id');
        $cityId = (int) $request->integer('city_id');

        $summary = [
            'total_warehouses' => 0,
            'active_warehouses' => 0,
            'stock_gudang' => 0,
            'barang_masuk' => 0,
            'barang_keluar' => 0,
        ];

        $provinces = collect();
        $cities = collect();
        $allCities = collect();
        $warehouseLocations = collect();

        if (Schema::hasTable('provinces')) {
            $provinces = DB::table('provinces')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        }

        if ($provinceId > 0 && Schema::hasTable('cities')) {
            $cities = DB::table('cities')
                ->select('id', 'province_id', 'name', 'type')
                ->where('province_id', $provinceId)
                ->orderBy('name')
                ->get();
        }

        if (Schema::hasTable('cities') && Schema::hasTable('provinces')) {
            $allCities = DB::table('cities as city')
                ->leftJoin('provinces as province', 'province.id', '=', 'city.province_id')
                ->select(
                    'city.id',
                    'city.province_id',
                    'city.name',
                    'city.type',
                    'province.name as province_name'
                )
                ->orderBy('province.name')
                ->orderBy('city.name')
                ->get();
        }

        if (Schema::hasTable('admin_products')) {
            $summary['stock_gudang'] = (int) DB::table('admin_products')->sum('stock_qty');
            $summary['barang_masuk'] = (int) DB::table('admin_products')->sum('stock_qty');
        }

        if (Schema::hasTable('admin_order_items')) {
            $summary['barang_keluar'] = (int) DB::table('admin_order_items')->sum('qty');
        }

        if (Schema::hasTable('warehouses')) {
            $summary['total_warehouses'] = (int) DB::table('warehouses')->count();
            $summary['active_warehouses'] = (int) DB::table('warehouses')->where('is_active', true)->count();

            $stockSubQuery = null;
            if (Schema::hasTable('admin_products')) {
                $stockSubQuery = DB::table('admin_products')
                    ->select(
                        'warehouse_id',
                        DB::raw('COUNT(*) as product_count'),
                        DB::raw('SUM(CASE WHEN is_active THEN 1 ELSE 0 END) as active_product_count'),
                        DB::raw('COALESCE(SUM(stock_qty), 0) as total_stock')
                    )
                    ->groupBy('warehouse_id');
            }

            $locationQuery = DB::table('warehouses as w')
                ->leftJoin('provinces as p', 'p.id', '=', 'w.province_id')
                ->leftJoin('cities as c', 'c.id', '=', 'w.city_id')
                ->select(
                    'w.id',
                    'w.code',
                    'w.name',
                    'w.province_id',
                    'w.city_id',
                    'w.address',
                    'w.notes',
                    'w.is_active',
                    'w.created_at',
                    'p.name as province_name',
                    'c.name as city_name',
                    'c.type as city_type',
                );

            if ($stockSubQuery !== null) {
                $locationQuery->leftJoinSub($stockSubQuery, 'stock', function ($join) {
                    $join->on('stock.warehouse_id', '=', 'w.id');
                })->addSelect(
                    DB::raw('COALESCE(stock.product_count, 0) as product_count'),
                    DB::raw('COALESCE(stock.active_product_count, 0) as active_product_count'),
                    DB::raw('COALESCE(stock.total_stock, 0) as total_stock'),
                );
            } else {
                $locationQuery->addSelect(
                    DB::raw('0 as product_count'),
                    DB::raw('0 as active_product_count'),
                    DB::raw('0 as total_stock'),
                );
            }

            if ($keyword !== '') {
                $locationQuery->where(function ($sub) use ($keyword) {
                    $sub->where('w.code', 'like', "%{$keyword}%")
                        ->orWhere('w.name', 'like', "%{$keyword}%")
                        ->orWhere('p.name', 'like', "%{$keyword}%")
                        ->orWhere('c.name', 'like', "%{$keyword}%");
                });
            }

            if ($provinceId > 0) {
                $locationQuery->where('w.province_id', $provinceId);
            }

            if ($cityId > 0) {
                $locationQuery->where('w.city_id', $cityId);
            }

            if (in_array($status, ['active', 'inactive'], true)) {
                $locationQuery->where('w.is_active', $status === 'active');
            }

            $warehouseLocations = $locationQuery
                ->orderByDesc('w.id')
                ->paginate(15)
                ->withQueryString();
        }

        return view('admin.warehouse', [
            'summary' => $summary,
            'warehouseLocations' => $warehouseLocations,
            'provinces' => $provinces,
            'cities' => $cities,
            'allCities' => $allCities,
            'filters' => [
                'q' => $keyword,
                'status' => $status,
                'province_id' => $provinceId > 0 ? (string) $provinceId : '',
                'city_id' => $cityId > 0 ? (string) $cityId : '',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('access-admin');

        if (! Schema::hasTable('warehouses')) {
            return back()->withErrors(['warehouse' => 'Fitur gudang belum tersedia. Jalankan migration terbaru.']);
        }

        $data = $this->validateWarehousePayload($request);

        DB::table('warehouses')->insert([
            'code' => $data['code'],
            'name' => $data['name'],
            'province_id' => $data['province_id'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'address' => $data['address'] ?: null,
            'notes' => $data['notes'] ?: null,
            'is_active' => (bool) $data['is_active'],
            'created_by' => (int) $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', "Gudang {$data['name']} berhasil ditambahkan.");
    }

    public function update(Request $request, int $warehouseId)
    {
        $this->authorize('access-admin');

        if (! Schema::hasTable('warehouses')) {
            return back()->withErrors(['warehouse' => 'Fitur gudang belum tersedia. Jalankan migration terbaru.']);
        }

        $warehouse = DB::table('warehouses')
            ->where('id', $warehouseId)
            ->first(['id', 'name']);

        if (! $warehouse) {
            return back()->withErrors(['warehouse' => 'Gudang tidak ditemukan.']);
        }

        $data = $this->validateWarehousePayload($request, $warehouseId);

        if (! (bool) $data['is_active']) {
            $this->assertWarehouseCanDeactivate($warehouseId);
        }

        DB::table('warehouses')
            ->where('id', $warehouseId)
            ->update([
                'code' => $data['code'],
                'name' => $data['name'],
                'province_id' => $data['province_id'] ?? null,
                'city_id' => $data['city_id'] ?? null,
                'address' => $data['address'] ?: null,
                'notes' => $data['notes'] ?: null,
                'is_active' => (bool) $data['is_active'],
                'updated_at' => now(),
            ]);

        return back()->with('status', "Gudang {$data['name']} berhasil diperbarui.");
    }

    public function toggleActive(Request $request, int $warehouseId)
    {
        $this->authorize('access-admin');

        if (! Schema::hasTable('warehouses')) {
            return back()->withErrors(['warehouse' => 'Fitur gudang belum tersedia. Jalankan migration terbaru.']);
        }

        $warehouse = DB::table('warehouses')
            ->where('id', $warehouseId)
            ->first(['id', 'name', 'is_active']);

        if (! $warehouse) {
            return back()->withErrors(['warehouse' => 'Gudang tidak ditemukan.']);
        }

        $nextActive = ! (bool) $warehouse->is_active;
        if (! $nextActive) {
            $this->assertWarehouseCanDeactivate($warehouseId);
        }

        DB::table('warehouses')
            ->where('id', $warehouseId)
            ->update([
                'is_active' => $nextActive,
                'updated_at' => now(),
            ]);

        return back()->with('status', $nextActive
            ? "Gudang {$warehouse->name} berhasil diaktifkan."
            : "Gudang {$warehouse->name} berhasil dinonaktifkan.");
    }

    private function nextWarehouseCode(): string
    {
        for ($i = 1; $i <= 9999; $i++) {
            $candidate = 'GDG-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $exists = DB::table('warehouses')->where('code', $candidate)->exists();
            if (! $exists) {
                return $candidate;
            }
        }

        return 'GDG-' . strtoupper(substr(md5((string) microtime(true)), 0, 6));
    }

    private function validateWarehousePayload(Request $request, ?int $warehouseId = null): array
    {
        $hasRegionTables = Schema::hasTable('provinces') && Schema::hasTable('cities');

        $rules = [
            'code' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];

        if ($hasRegionTables) {
            $rules['province_id'] = ['required', 'integer', 'exists:provinces,id'];
            $rules['city_id'] = ['required', 'integer', 'exists:cities,id'];
        } else {
            $rules['province_id'] = ['nullable', 'integer'];
            $rules['city_id'] = ['nullable', 'integer'];
        }

        $data = $request->validate($rules);

        if ($hasRegionTables) {
            $cityMatchesProvince = DB::table('cities')
                ->where('id', (int) $data['city_id'])
                ->where('province_id', (int) $data['province_id'])
                ->exists();

            if (! $cityMatchesProvince) {
                throw ValidationException::withMessages([
                    'city_id' => 'Kota/Kabupaten tidak sesuai dengan provinsi yang dipilih.',
                ]);
            }
        }

        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($code === '') {
            if ($warehouseId !== null) {
                $currentCode = (string) (DB::table('warehouses')
                    ->where('id', $warehouseId)
                    ->value('code') ?? '');
                $code = $currentCode !== '' ? $currentCode : $this->nextWarehouseCode();
            } else {
                $code = $this->nextWarehouseCode();
            }
        }

        $duplicateCodeQuery = DB::table('warehouses')
            ->whereRaw('LOWER(code) = ?', [strtolower($code)]);
        if ($warehouseId !== null) {
            $duplicateCodeQuery->where('id', '<>', $warehouseId);
        }

        if ($duplicateCodeQuery->exists()) {
            throw ValidationException::withMessages([
                'code' => 'Kode gudang sudah digunakan. Gunakan kode lain.',
            ]);
        }

        return [
            'code' => $code,
            'name' => trim((string) ($data['name'] ?? '')),
            'province_id' => isset($data['province_id']) ? (int) $data['province_id'] : null,
            'city_id' => isset($data['city_id']) ? (int) $data['city_id'] : null,
            'address' => trim((string) ($data['address'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }

    private function assertWarehouseCanDeactivate(int $warehouseId): void
    {
        if (! Schema::hasTable('admin_products')) {
            return;
        }

        $hasActiveProducts = DB::table('admin_products')
            ->where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->exists();

        if ($hasActiveProducts) {
            throw ValidationException::withMessages([
                'is_active' => 'Gudang tidak bisa dinonaktifkan karena masih ada produk pengadaan aktif.',
            ]);
        }

        $hasRemainingStock = DB::table('admin_products')
            ->where('warehouse_id', $warehouseId)
            ->where('stock_qty', '>', 0)
            ->exists();

        if ($hasRemainingStock) {
            throw ValidationException::withMessages([
                'is_active' => 'Gudang tidak bisa dinonaktifkan karena stok produk masih tersedia.',
            ]);
        }
    }
}
