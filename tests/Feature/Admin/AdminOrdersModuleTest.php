<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminOrdersModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_orders_module_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.modules.orders'));

        $response->assertOk();
        $response->assertSee('data-testid="admin-orders-page"', false);
        $response->assertSee('data-testid="admin-orders-summary"', false);
    }

    public function test_admin_can_filter_orders_by_affiliate_source_and_keyword(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyerAffiliate = User::factory()->create(['role' => 'consumer', 'name' => 'Buyer Affiliate', 'email' => 'buyer.affiliate@example.test']);
        $buyerNonAffiliate = User::factory()->create(['role' => 'consumer', 'name' => 'Buyer Non Affiliate', 'email' => 'buyer.non.affiliate@example.test']);
        $seller = User::factory()->create(['role' => 'mitra', 'email' => 'seller.order@example.test']);

        $affiliateOrderId = DB::table('orders')->insertGetId([
                'buyer_id' => $buyerAffiliate->id,
                'seller_id' => $seller->id,
                'order_source' => 'farmer_p2p',
                'total_amount' => 120000,
                'payment_status' => 'paid',
                'order_status' => 'completed',
                'payment_proof_url' => null,
                'shipping_status' => 'delivered',
                'resi_number' => 'RESI123',
                'created_at' => now(),
                'updated_at' => now(),
        ]);

        $nonAffiliateOrderId = DB::table('orders')->insertGetId([
                'buyer_id' => $buyerNonAffiliate->id,
                'seller_id' => $seller->id,
                'order_source' => 'store_online',
                'total_amount' => 65000,
                'payment_status' => 'paid',
                'order_status' => 'completed',
                'payment_proof_url' => null,
                'shipping_status' => 'delivered',
                'resi_number' => null,
                'created_at' => now(),
                'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            [
                'order_id' => $affiliateOrderId,
                'product_id' => 101,
                'product_name' => 'Produk Affiliate',
                'qty' => 1,
                'price_per_unit' => 120000,
                'affiliate_id' => $admin->id,
                'commission_amount' => 1000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $nonAffiliateOrderId,
                'product_id' => 102,
                'product_name' => 'Produk Non Affiliate',
                'qty' => 1,
                'price_per_unit' => 65000,
                'affiliate_id' => null,
                'commission_amount' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.modules.orders', [
            'affiliate_source' => 'affiliate',
            'q' => 'buyer.affiliate@example.test',
        ]));

        $response->assertOk();
        $response->assertSee('data-testid="admin-orders-table"', false);
        $response->assertSee('buyer.affiliate@example.test');
        $response->assertDontSee('buyer.non.affiliate@example.test');
    }
}
