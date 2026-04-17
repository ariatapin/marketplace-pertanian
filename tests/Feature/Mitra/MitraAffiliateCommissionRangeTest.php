<?php

namespace Tests\Feature\Mitra;

use App\Models\StoreProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MitraAffiliateCommissionRangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_mitra_cannot_set_affiliate_commission_below_admin_minimum(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        DB::table('admin_profiles')->updateOrInsert(
            ['user_id' => $admin->id],
            [
                'affiliate_commission_min_percent' => 5.00,
                'affiliate_commission_max_percent' => 20.00,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $mitra = User::factory()->create(['role' => 'mitra']);
        $product = StoreProduct::query()->create([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Uji Komisi',
            'description' => 'Produk untuk verifikasi range komisi affiliate.',
            'price' => 120000,
            'unit' => 'kg',
            'stock_qty' => 25,
            'image_url' => 'products/test.jpg',
            'is_active' => true,
            'is_affiliate_enabled' => true,
            'affiliate_commission' => 10.00,
            'affiliate_expire_date' => now()->addDays(14)->toDateString(),
        ]);

        $response = $this->actingAs($mitra)
            ->from(route('mitra.products.index'))
            ->post(route('mitra.products.marketplaceSettings', ['product' => $product->id]), [
                'is_affiliate_enabled' => 1,
                'affiliate_commission' => 3,
                'affiliate_expire_date' => now()->addDays(21)->toDateString(),
            ]);

        $response->assertRedirect(route('mitra.products.index'));
        $response->assertSessionHasErrors('affiliate_commission');

        $this->assertDatabaseHas('store_products', [
            'id' => $product->id,
            'affiliate_commission' => 10.00,
        ]);
    }

    public function test_mitra_can_set_affiliate_commission_within_admin_range(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        DB::table('admin_profiles')->updateOrInsert(
            ['user_id' => $admin->id],
            [
                'affiliate_commission_min_percent' => 4.00,
                'affiliate_commission_max_percent' => 18.00,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $mitra = User::factory()->create(['role' => 'mitra']);
        $product = StoreProduct::query()->create([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Uji Komisi Valid',
            'description' => 'Produk untuk verifikasi komisi valid.',
            'price' => 90000,
            'unit' => 'kg',
            'stock_qty' => 30,
            'image_url' => 'products/test-valid.jpg',
            'is_active' => true,
            'is_affiliate_enabled' => true,
            'affiliate_commission' => 8.00,
            'affiliate_expire_date' => now()->addDays(7)->toDateString(),
        ]);

        $response = $this->actingAs($mitra)
            ->from(route('mitra.products.index'))
            ->post(route('mitra.products.marketplaceSettings', ['product' => $product->id]), [
                'is_affiliate_enabled' => 1,
                'affiliate_commission' => 12.5,
                'affiliate_expire_date' => now()->addDays(30)->toDateString(),
            ]);

        $response->assertRedirect(route('mitra.products.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('store_products', [
            'id' => $product->id,
            'is_affiliate_enabled' => true,
            'affiliate_commission' => 12.50,
        ]);
    }
}
