<?php

namespace Tests\Feature\Mitra;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MitraDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_mitra_dashboard_can_render_with_metrics_and_sections(): void
    {
        $mitra = User::factory()->create([
            'role' => 'mitra',
            'email' => 'mitra.dashboard@example.test',
        ]);

        DB::table('store_products')->insert([
            [
                'mitra_id' => $mitra->id,
                'name' => 'Produk A',
                'description' => null,
                'price' => 10000,
                'stock_qty' => 5,
                'image_url' => null,
                'is_affiliate_enabled' => false,
                'affiliate_commission' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'mitra_id' => $mitra->id,
                'name' => 'Produk B',
                'description' => null,
                'price' => 15000,
                'stock_qty' => 0,
                'image_url' => null,
                'is_affiliate_enabled' => false,
                'affiliate_commission' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 50000,
            'status' => 'pending',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminProductId = DB::table('admin_products')->insertGetId([
            'name' => 'Pupuk A',
            'description' => null,
            'price' => 5000,
            'stock_qty' => 100,
            'min_order_qty' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_order_items')->insert([
            'admin_order_id' => $orderId,
            'admin_product_id' => $adminProductId,
            'product_name' => 'Pupuk A',
            'price_per_unit' => 5000,
            'qty' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($mitra)->get(route('mitra.dashboard'));

        $response->assertOk();
        $response->assertSee('data-testid="mitra-dashboard-page"', false);
        $response->assertSee('data-testid="mitra-dashboard-hero"', false);
        $response->assertSee('data-testid="mitra-dashboard-products"', false);
        $response->assertSee('Produk A');
        $response->assertSee('Produk B');
        $response->assertDontSee(route('notifications.index', ['type' => 'all']));
    }
}
