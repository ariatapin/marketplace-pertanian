<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminWarehouseModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_warehouse_module_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.modules.warehouse'));

        $response->assertOk();
        $response->assertSee('Modul Gudang');
        $response->assertSee('Tambah Gudang');
    }

    public function test_warehouse_page_does_not_generate_auto_warehouse_from_region(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        DB::table('provinces')->insert([
            'name' => 'Jawa Tengah',
            'code' => 'JATENG',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.modules.warehouse'));

        $response->assertOk();
        $response->assertSee('Belum ada data gudang. Tambahkan gudang pertama dari form di atas.');
        $response->assertDontSee('Gudang Jawa Tengah');
    }

    public function test_admin_can_add_warehouse_from_warehouse_module(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $provinceId = DB::table('provinces')->insertGetId([
            'name' => 'DKI Jakarta',
            'code' => 'DKI',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId,
            'name' => 'Jakarta Timur',
            'type' => 'Kota',
            'lat' => -6.2250140,
            'lng' => 106.9004470,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.modules.warehouse'))
            ->post(route('admin.modules.warehouse.store'), [
                'code' => 'GDG-JKT-ET-01',
                'name' => 'Gudang Jakarta Timur',
                'province_id' => $provinceId,
                'city_id' => $cityId,
                'address' => 'Jl. Pengadaan No. 10',
                'notes' => 'Gudang utama distribusi Jakarta Timur',
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('admin.modules.warehouse'));
        $this->assertDatabaseHas('warehouses', [
            'code' => 'GDG-JKT-ET-01',
            'name' => 'Gudang Jakarta Timur',
            'province_id' => $provinceId,
            'city_id' => $cityId,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_warehouse_from_module(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $provinceA = DB::table('provinces')->insertGetId([
            'name' => 'DKI Jakarta',
            'code' => 'DKI',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cityA = DB::table('cities')->insertGetId([
            'province_id' => $provinceA,
            'name' => 'Jakarta Timur',
            'type' => 'Kota',
            'lat' => -6.2250140,
            'lng' => 106.9004470,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $provinceB = DB::table('provinces')->insertGetId([
            'name' => 'Jawa Barat',
            'code' => 'JABAR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cityB = DB::table('cities')->insertGetId([
            'province_id' => $provinceB,
            'name' => 'Bandung',
            'type' => 'Kota',
            'lat' => -6.9174640,
            'lng' => 107.6191230,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $warehouseId = DB::table('warehouses')->insertGetId([
            'code' => 'GDG-EDIT-01',
            'name' => 'Gudang Lama',
            'province_id' => $provinceA,
            'city_id' => $cityA,
            'address' => 'Alamat lama',
            'notes' => null,
            'is_active' => true,
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.modules.warehouse'))
            ->patch(route('admin.modules.warehouse.update', ['warehouseId' => $warehouseId]), [
                'code' => 'GDG-EDIT-99',
                'name' => 'Gudang Baru Bandung',
                'province_id' => $provinceB,
                'city_id' => $cityB,
                'address' => 'Alamat baru',
                'notes' => 'Catatan update',
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('admin.modules.warehouse'));
        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouseId,
            'code' => 'GDG-EDIT-99',
            'name' => 'Gudang Baru Bandung',
            'province_id' => $provinceB,
            'city_id' => $cityB,
            'address' => 'Alamat baru',
            'notes' => 'Catatan update',
            'is_active' => true,
        ]);
    }

    public function test_admin_cannot_deactivate_warehouse_when_active_products_still_exist(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $provinceId = DB::table('provinces')->insertGetId([
            'name' => 'Jawa Timur',
            'code' => 'JATIM',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId,
            'name' => 'Surabaya',
            'type' => 'Kota',
            'lat' => -7.2574720,
            'lng' => 112.7520880,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $warehouseId = DB::table('warehouses')->insertGetId([
            'code' => 'GDG-LOCK-01',
            'name' => 'Gudang Terkunci',
            'province_id' => $provinceId,
            'city_id' => $cityId,
            'address' => 'Alamat',
            'notes' => null,
            'is_active' => true,
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_products')->insert([
            'name' => 'Produk Aktif Gudang',
            'description' => null,
            'price' => 12000,
            'unit' => 'kg',
            'min_order_qty' => 1,
            'stock_qty' => 50,
            'is_active' => true,
            'warehouse_id' => $warehouseId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.modules.warehouse'))
            ->post(route('admin.modules.warehouse.toggleActive', ['warehouseId' => $warehouseId]));

        $response->assertRedirect(route('admin.modules.warehouse'));
        $response->assertSessionHasErrors('is_active');
        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouseId,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_toggle_warehouse_to_inactive_when_safe(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $provinceId = DB::table('provinces')->insertGetId([
            'name' => 'Aceh',
            'code' => 'ACEH',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId,
            'name' => 'Banda Aceh',
            'type' => 'Kota',
            'lat' => 5.5482900,
            'lng' => 95.3237530,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $warehouseId = DB::table('warehouses')->insertGetId([
            'code' => 'GDG-TOGGLE-01',
            'name' => 'Gudang Toggle',
            'province_id' => $provinceId,
            'city_id' => $cityId,
            'address' => 'Alamat',
            'notes' => null,
            'is_active' => true,
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.modules.warehouse'))
            ->post(route('admin.modules.warehouse.toggleActive', ['warehouseId' => $warehouseId]));

        $response->assertRedirect(route('admin.modules.warehouse'));
        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouseId,
            'is_active' => false,
        ]);
    }
}
