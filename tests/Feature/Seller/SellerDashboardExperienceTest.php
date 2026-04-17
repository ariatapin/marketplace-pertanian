<?php

namespace Tests\Feature\Seller;

use App\Models\User;
use App\Support\AdminWeatherNoticeNotification;
use App\Support\BehaviorRecommendationNotification;
use App\Support\PaymentOrderStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SellerDashboardExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_dashboard_hides_legacy_role_mode_labels_and_keeps_main_actions(): void
    {
        $sellerModeUser = User::factory()->create(['role' => 'consumer']);
        $this->seedSellerMode($sellerModeUser->id);

        $response = $this->actingAs($sellerModeUser)->get(route('seller.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Role: Consumer');
        $response->assertDontSee('Mode Aktif: Penjual');
        $response->assertSee('Seller Control');
        $response->assertSee('Saldo Wallet');
        $response->assertSee('Kelola Produk');
        $response->assertSee('Produk Petani di Marketplace');
        $response->assertSee('Notifikasi');
    }

    public function test_seller_dashboard_only_shows_order_and_admin_notifications(): void
    {
        $sellerModeUser = User::factory()->create(['role' => 'consumer']);
        $this->seedSellerMode($sellerModeUser->id);

        $sellerModeUser->notify(new PaymentOrderStatusNotification(
            status: 'pending',
            title: 'Order Baru dari Buyer',
            message: 'Buyer mengirim bukti pembayaran dan menunggu verifikasi.',
            actionUrl: route('seller.dashboard', absolute: false) . '#seller-order-latest',
            actionLabel: 'Cek Order',
            orderId: 901,
            paymentMethod: 'bank_transfer',
            paidAmount: 120000,
            dispatchKey: 'seller-order-test-' . now()->format('YmdHisv')
        ));

        $sellerModeUser->notify(new AdminWeatherNoticeNotification(
            severity: 'yellow',
            title: 'Info Admin Cuaca',
            message: 'Admin mengirim notifikasi operasional untuk area Anda.',
            actionUrl: route('notifications.index', absolute: false),
            actionLabel: 'Buka Notifikasi',
            scope: 'region',
            targetLabel: 'Kota',
            validUntil: now()->addHours(6)->toDateTimeString(),
            noticeId: 77,
            dispatchKey: 'seller-admin-test-' . now()->format('YmdHisv')
        ));

        $sellerModeUser->notify(new BehaviorRecommendationNotification(
            status: 'green',
            title: 'Noise Recommendation',
            message: 'Ini tidak boleh tampil pada notifikasi dashboard seller.',
            roleTarget: 'seller',
            ruleKey: 'seller_noise_rule',
            dispatchKey: 'seller-noise-' . now()->format('YmdHisv'),
            targetLabel: 'Noise',
            validUntil: now()->addHour()->toDateTimeString(),
            actionUrl: '/seller/dashboard',
            actionLabel: 'Lihat'
        ));

        $response = $this->actingAs($sellerModeUser)->get(route('seller.dashboard'));

        $response->assertOk();
        $response->assertSee('Notifikasi Dashboard Penjual');
        $response->assertSee('Order Baru dari Buyer');
        $response->assertSee('Info Admin Cuaca');
        $response->assertSee('Order Pembeli');
        $response->assertSee('Admin');
        $response->assertDontSee('Noise Recommendation');
    }

    public function test_seller_order_quick_action_can_mark_order_packed_and_shipped(): void
    {
        $sellerModeUser = User::factory()->create(['role' => 'consumer']);
        $this->seedSellerMode($sellerModeUser->id);
        $buyer = User::factory()->create(['role' => 'consumer']);

        $orderId = (int) DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $sellerModeUser->id,
            'order_source' => 'farmer_p2p',
            'total_amount' => 180000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'paid',
            'order_status' => 'paid',
            'shipping_status' => 'pending',
            'payment_proof_url' => null,
            'paid_amount' => 180000,
            'payment_submitted_at' => now()->subMinutes(20),
            'resi_number' => null,
            'created_at' => now()->subMinutes(25),
            'updated_at' => now()->subMinutes(20),
        ]);

        $this->actingAs($sellerModeUser)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('Aksi')
            ->assertSee('Tandai Packed');

        $this->actingAs($sellerModeUser)
            ->from(route('seller.dashboard'))
            ->post(route('seller.orders.markPacked', ['orderId' => $orderId]))
            ->assertRedirect(route('seller.dashboard'));

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'order_status' => 'packed',
        ]);
        $this->assertDatabaseHas('shipments', [
            'order_id' => $orderId,
            'status' => 'pending',
        ]);

        $this->actingAs($sellerModeUser)
            ->from(route('seller.dashboard'))
            ->post(route('seller.orders.markShipped', ['orderId' => $orderId]))
            ->assertRedirect(route('seller.dashboard'));

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'order_status' => 'shipped',
            'shipping_status' => 'shipped',
        ]);
        $this->assertDatabaseHas('shipments', [
            'order_id' => $orderId,
            'status' => 'shipped',
        ]);
    }

    public function test_dashboard_shows_only_three_latest_orders_and_links_to_order_page(): void
    {
        $sellerModeUser = User::factory()->create(['role' => 'consumer']);
        $this->seedSellerMode($sellerModeUser->id);

        for ($i = 1; $i <= 4; $i++) {
            $buyer = User::factory()->create(['role' => 'consumer']);
            DB::table('orders')->insert([
                'buyer_id' => $buyer->id,
                'seller_id' => $sellerModeUser->id,
                'order_source' => 'farmer_p2p',
                'total_amount' => 100000 + ($i * 1000),
                'payment_method' => 'bank_transfer',
                'payment_status' => 'paid',
                'order_status' => 'paid',
                'shipping_status' => 'pending',
                'payment_proof_url' => null,
                'paid_amount' => 100000 + ($i * 1000),
                'payment_submitted_at' => now()->subMinutes(10),
                'resi_number' => null,
                'created_at' => now()->subMinutes(10 - $i),
                'updated_at' => now()->subMinutes(10 - $i),
            ]);
        }

        $dashboardResponse = $this->actingAs($sellerModeUser)->get(route('seller.dashboard'));
        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('Order Lainnya');
        $this->assertSame(3, substr_count($dashboardResponse->getContent(), 'Tandai Packed'));

        $ordersPageResponse = $this->actingAs($sellerModeUser)->get(route('seller.orders.index'));
        $ordersPageResponse->assertOk();
        $ordersPageResponse->assertSee('Semua Order Pembeli');
        $ordersPageResponse->assertSee('Order');
        $ordersPageResponse->assertSee('Aksi');
        $this->assertSame(4, substr_count($ordersPageResponse->getContent(), 'Tandai Packed'));
    }

    private function seedSellerMode(int $userId): void
    {
        DB::table('consumer_profiles')->insert([
            'user_id' => $userId,
            'address' => null,
            'mode' => 'farmer_seller',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
