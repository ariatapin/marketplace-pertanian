<?php

namespace Tests\Feature\Affiliate;

use App\Models\User;
use App\Services\AffiliateReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AffiliateReferralFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_product_cart_item_receives_affiliate_referral_from_landing_link(): void
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

        $productId = (int) DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Pupuk Kompos Mitra',
            'description' => 'Produk mitra untuk test referral.',
            'price' => 50000,
            'stock_qty' => 100,
            'image_url' => null,
            'is_affiliate_enabled' => true,
            'affiliate_commission' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $refCode = app(AffiliateReferralService::class)->encodeReferralCode($affiliate->id);

        $this->actingAs($buyer)
            ->get('/?ref=' . $refCode)
            ->assertOk();

        $this->actingAs($buyer)
            ->post('/cart', [
                'product_id' => $productId,
                'product_type' => 'store',
                'qty' => 2,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $buyer->id,
            'product_type' => 'store',
            'product_id' => $productId,
            'affiliate_referral_id' => $affiliate->id,
        ]);

        $this->assertDatabaseHas('affiliate_locks', [
            'affiliate_id' => $affiliate->id,
            'product_id' => $productId,
            'is_active' => true,
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
        ]);

        // Retry add-to-cart should not create duplicate active lock for the same affiliate-product pair.
        $this->actingAs($buyer)
            ->post('/cart', [
                'product_id' => $productId,
                'product_type' => 'store',
                'qty' => 1,
            ])
            ->assertRedirect();

        $this->assertSame(
            1,
            (int) DB::table('affiliate_locks')
                ->where('affiliate_id', $affiliate->id)
                ->where('product_id', $productId)
                ->where('is_active', true)
                ->count()
        );
    }

    public function test_farmer_product_cart_item_does_not_receive_affiliate_referral(): void
    {
        $affiliate = User::factory()->create(['role' => 'consumer']);
        $buyer = User::factory()->create(['role' => 'consumer']);
        $farmerSeller = User::factory()->create(['role' => 'farmer_seller']);

        DB::table('consumer_profiles')->insert([
            'user_id' => $affiliate->id,
            'address' => null,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $harvestId = (int) DB::table('farmer_harvests')->insertGetId([
            'farmer_id' => $farmerSeller->id,
            'name' => 'Cabai Rawit',
            'description' => 'Produk petani.',
            'price' => 30000,
            'stock_qty' => 40,
            'harvest_date' => now()->toDateString(),
            'image_url' => null,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $refCode = app(AffiliateReferralService::class)->encodeReferralCode($affiliate->id);

        $this->actingAs($buyer)
            ->get('/?ref=' . $refCode)
            ->assertOk();

        $this->actingAs($buyer)
            ->post('/cart', [
                'product_id' => $harvestId,
                'product_type' => 'farmer',
                'qty' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $buyer->id,
            'product_type' => 'farmer',
            'product_id' => $harvestId,
            'affiliate_referral_id' => null,
        ]);
    }

    public function test_self_referral_is_blocked_for_affiliate_user(): void
    {
        $affiliateBuyer = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        DB::table('consumer_profiles')->insert([
            'user_id' => $affiliateBuyer->id,
            'address' => null,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = (int) DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Benih Jagung',
            'description' => 'Produk mitra untuk test self-referral.',
            'price' => 45000,
            'stock_qty' => 50,
            'image_url' => null,
            'is_affiliate_enabled' => true,
            'affiliate_commission' => 8,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $refCode = app(AffiliateReferralService::class)->encodeReferralCode($affiliateBuyer->id);

        $this->actingAs($affiliateBuyer)
            ->get('/?ref=' . $refCode)
            ->assertOk();

        $this->actingAs($affiliateBuyer)
            ->post('/cart', [
                'product_id' => $productId,
                'product_type' => 'store',
                'qty' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $affiliateBuyer->id,
            'product_type' => 'store',
            'product_id' => $productId,
            'affiliate_referral_id' => null,
        ]);
    }

    public function test_store_product_lock_uses_admin_configured_lock_days(): void
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

        DB::table('feature_flags')->insert([
            'key' => 'affiliate_lock_policy',
            'is_enabled' => true,
            'description' => json_encode([
                'lock_days' => 14,
                'refresh_on_repromote' => false,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = (int) DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Lock 14 Hari',
            'description' => 'Produk mitra untuk test lock policy.',
            'price' => 49000,
            'stock_qty' => 80,
            'image_url' => null,
            'is_affiliate_enabled' => true,
            'affiliate_commission' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $refCode = app(AffiliateReferralService::class)->encodeReferralCode($affiliate->id);

        $this->actingAs($buyer)
            ->get('/?ref=' . $refCode)
            ->assertOk();

        $this->actingAs($buyer)
            ->post('/cart', [
                'product_id' => $productId,
                'product_type' => 'store',
                'qty' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('affiliate_locks', [
            'affiliate_id' => $affiliate->id,
            'product_id' => $productId,
            'is_active' => true,
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(14)->toDateString(),
        ]);
    }
}
