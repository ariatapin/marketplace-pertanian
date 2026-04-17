<?php

namespace Tests\Feature\Affiliate;

use App\Models\User;
use App\Services\AffiliateReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AffiliateReferralTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_referral_flow_records_click_cart_checkout_and_updates_dashboard_tracking_summary(): void
    {
        $affiliate = User::factory()->create(['role' => 'consumer']);
        $buyer = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        DB::table('consumer_profiles')->insert([
            'user_id' => $affiliate->id,
            'address' => null,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('consumer_profiles')->insert([
            'user_id' => $buyer->id,
            'address' => null,
            'mode' => 'farmer_seller',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = (int) DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Mitra Tracking',
            'description' => 'Produk untuk tracking affiliate.',
            'price' => 90000,
            'stock_qty' => 100,
            'image_url' => null,
            'is_active' => true,
            'is_affiliate_enabled' => true,
            'affiliate_commission' => 10,
            'affiliate_expire_date' => now()->addDays(5)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $refCode = app(AffiliateReferralService::class)->encodeReferralCode($affiliate->id);

        $this->actingAs($buyer)
            ->get(route('landing', ['ref' => $refCode]))
            ->assertOk();

        $this->actingAs($buyer)
            ->post(route('cart.store'), [
                'product_id' => $productId,
                'product_type' => 'store',
                'qty' => 1,
                'buy_now' => 0,
            ])
            ->assertRedirect();

        $this->actingAs($buyer)
            ->post(route('checkout'), [
                'payment_method' => 'bank_transfer',
            ])
            ->assertRedirect(route('orders.mine'));

        $orderId = (int) DB::table('orders')
            ->where('buyer_id', $buyer->id)
            ->where('seller_id', $mitra->id)
            ->latest('id')
            ->value('id');

        DB::table('orders')
            ->where('id', $orderId)
            ->update([
                'order_status' => 'completed',
                'payment_status' => 'paid',
                'shipping_status' => 'delivered',
                'paid_amount' => 90000,
                'payment_submitted_at' => now(),
                'updated_at' => now(),
            ]);

        $this->assertDatabaseHas('affiliate_referral_events', [
            'affiliate_user_id' => $affiliate->id,
            'event_type' => 'click',
        ]);
        $this->assertDatabaseHas('affiliate_referral_events', [
            'affiliate_user_id' => $affiliate->id,
            'actor_user_id' => $buyer->id,
            'event_type' => 'add_to_cart',
            'product_id' => $productId,
        ]);
        $this->assertDatabaseHas('affiliate_referral_events', [
            'affiliate_user_id' => $affiliate->id,
            'actor_user_id' => $buyer->id,
            'event_type' => 'checkout_created',
            'order_id' => $orderId,
        ]);

        $response = $this->actingAs($affiliate)->get(route('affiliate.dashboard'));
        $response->assertOk();
        $response->assertSee('Saldo Wallet');
        $response->assertSee('Total Komisi');
        $response->assertDontSee('Klik Link');
        $response->assertDontSee('Pesanan Saya');
        $response->assertViewHas('trackingSummary', function (array $summary): bool {
            return (int) ($summary['clicks'] ?? 0) >= 1
                && (int) ($summary['add_to_cart'] ?? 0) >= 1
                && (int) ($summary['checkout_created'] ?? 0) >= 1
                && (int) ($summary['completed_orders'] ?? 0) >= 1;
        });
    }

    public function test_referral_click_is_deduplicated_per_session_window(): void
    {
        $affiliate = User::factory()->create(['role' => 'consumer']);
        $buyer = User::factory()->create(['role' => 'consumer']);

        DB::table('consumer_profiles')->insert([
            'user_id' => $affiliate->id,
            'address' => null,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $refCode = app(AffiliateReferralService::class)->encodeReferralCode($affiliate->id);

        $this->actingAs($buyer)->get(route('landing', ['ref' => $refCode]))->assertOk();
        $this->actingAs($buyer)->get(route('landing', ['ref' => $refCode]))->assertOk();

        $clickCount = (int) DB::table('affiliate_referral_events')
            ->where('affiliate_user_id', $affiliate->id)
            ->where('event_type', 'click')
            ->count();

        $this->assertSame(1, $clickCount);
    }
}
