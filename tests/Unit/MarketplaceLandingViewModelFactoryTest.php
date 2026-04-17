<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\MarketplaceLandingViewModelFactory;
use Illuminate\Support\Collection;
use Tests\TestCase;

class MarketplaceLandingViewModelFactoryTest extends TestCase
{
    public function test_it_builds_landing_view_model_for_logged_in_user(): void
    {
        $factory = new MarketplaceLandingViewModelFactory();
        $user = new User([
            'name' => 'Petani Satu',
            'email' => 'petani@example.test',
            'role' => 'consumer',
            'google_avatar' => 'https://avatar.example.test/u.png',
        ]);

        $result = $factory->make($user, [
            'consumerSummary' => [
                'location_label' => 'Kab. Sleman',
                'mode' => 'affiliate',
                'mode_status' => 'approved',
            ],
            'isActiveConsumerDashboard' => true,
            'marketStats' => [
                'in_stock_products' => 12,
                'active_sellers' => 4,
                'average_price' => 22000,
            ],
            'featuredProducts' => new Collection([
                (object) [
                    'id' => 3,
                    'name' => 'Beras Organik',
                    'description' => null,
                    'price' => 45000,
                    'stock_qty' => 7,
                    'image_url' => 'products/beras.png',
                    'mitra_name' => 'Mitra Beras',
                    'updated_at' => now()->subHour(),
                ],
            ]),
            'heroAnnouncements' => new Collection([
                (object) [
                    'id' => 5,
                    'type' => 'promo',
                    'title' => 'Promo Panen',
                    'message' => 'Diskon khusus musim panen.',
                    'cta_label' => 'Lihat Promo',
                    'cta_url' => 'https://example.test/promo',
                ],
            ]),
            'unreadNotifications' => 2,
            'searchKeyword' => 'beras',
            'cartSummary' => [
                'items' => 3,
                'estimated_total' => 90000,
            ],
            'mitraSubmission' => [
                'open' => true,
                'title' => 'Pengajuan Mitra Dibuka',
            ],
        ]);

        $this->assertSame('consumer', $result['role']);
        $this->assertSame('Kab. Sleman', $result['weatherLocationLabel']);
        $this->assertSame(route('cart.index'), $result['cartTarget']);
        $this->assertNotNull($result['accountMenu']);
        $this->assertSame('Petani Satu', $result['accountMenu']['name']);
        $this->assertSame('P', $result['accountMenu']['avatar_initial']);
        $this->assertSame(1, $result['featuredProductsCount']);
        $this->assertSame('Rp45.000', $result['featuredProductCards'][0]['price_label']);
        $this->assertStringContainsString('/images/product-placeholder.svg', $result['featuredProductCards'][0]['image_src']);
        $this->assertTrue($result['heroAnnouncementCards'][0]['is_external_cta']);
        $this->assertSame('PROMO', $result['heroAnnouncementCards'][0]['type_label']);
    }

    public function test_it_builds_guest_defaults_when_user_is_null(): void
    {
        $factory = new MarketplaceLandingViewModelFactory();

        $result = $factory->make(null, []);

        $this->assertNull($result['accountMenu']);
        $this->assertSame(route('landing', ['auth' => 'login']), $result['cartTarget']);
        $this->assertSame('Atur lokasi agar prediksi cuaca lebih akurat', $result['weatherLocationLabel']);
        $this->assertSame(0, $result['featuredProductsCount']);
    }

    public function test_it_builds_affiliate_share_url_to_product_detail_page(): void
    {
        $factory = new MarketplaceLandingViewModelFactory();
        $user = new User([
            'name' => 'Affiliate Satu',
            'email' => 'affiliate@example.test',
            'role' => 'consumer',
        ]);
        $refCode = '16.' . str_repeat('d', 24);

        $result = $factory->make($user, [
            'canUseAffiliateProductFilter' => true,
            'affiliateSelfReferralCode' => $refCode,
            'affiliateReadyOnly' => true,
            'affiliateReadyCount' => 3,
            'productSource' => 'affiliate',
            'featuredProducts' => new Collection([
                (object) [
                    'id' => 22,
                    'product_type' => 'store',
                    'seller_kind' => 'mitra',
                    'seller_id' => 9,
                    'name' => 'Pupuk Premium',
                    'description' => 'Produk affiliate.',
                    'price' => 56000,
                    'stock_qty' => 20,
                    'is_affiliate_enabled' => true,
                    'is_marketed_by_affiliate' => true,
                    'updated_at' => now(),
                ],
            ]),
        ]);

        $expectedUrl = route('marketplace.product.show', [
            'productType' => 'store',
            'productId' => 22,
            'ref' => $refCode,
            'source' => 'affiliate',
        ]);

        $this->assertSame($expectedUrl, $result['featuredProductCards'][0]['affiliate_share_url']);
        $this->assertTrue((bool) $result['featuredProductCards'][0]['show_affiliate_badge']);
        $this->assertTrue((bool) $result['featuredProductCards'][0]['is_marketed_by_affiliate']);
        $this->assertTrue((bool) $result['affiliateReadyOnly']);
        $this->assertSame(3, $result['affiliateReadyCount']);
    }
}
