<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PaymentOrderStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentNotificationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_payment_proof_dispatches_notifications_to_buyer_seller_and_admin(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create(['role' => 'consumer', 'name' => 'Buyer Demo']);
        $seller = User::factory()->create(['role' => 'mitra', 'name' => 'Seller Demo']);
        $this->seedApprovedConsumerMode($buyer->id, 'farmer_seller');

        $productId = DB::table('store_products')->insertGetId([
            'mitra_id' => $seller->id,
            'name' => 'Benih Jagung',
            'description' => 'Benih jagung unggul.',
            'price' => 60000,
            'stock_qty' => 10,
            'image_url' => null,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($buyer)->post(route('cart.store'), [
            'product_id' => $productId,
            'qty' => 1,
        ])->assertRedirect();

        $this->actingAs($buyer)
            ->post(route('checkout'), [
                'payment_method' => 'bank_transfer',
            ])
            ->assertRedirect(route('orders.mine'));

        $order = DB::table('orders')->where('buyer_id', $buyer->id)->first();
        $this->assertNotNull($order);

        $this->actingAs($buyer)
            ->post(route('orders.transfer-proof', ['orderId' => $order->id]), [
                'payment_method' => 'bank_transfer',
                'paid_amount' => 60000,
                'proof' => UploadedFile::fake()->createWithContent('proof.jpg', 'payment-proof'),
            ])
            ->assertRedirect();

        $buyerNotif = $this->latestPaymentNotification((int) $buyer->id, 'pending');
        $sellerNotif = $this->latestPaymentNotification((int) $seller->id, 'pending');
        $adminNotif = $this->latestPaymentNotification((int) $admin->id, 'pending');

        $this->assertNotNull($buyerNotif);
        $this->assertNotNull($sellerNotif);
        $this->assertNotNull($adminNotif);
        $this->assertSame((int) $order->id, (int) ($buyerNotif['order_id'] ?? 0));
        $this->assertSame('bank_transfer', (string) ($sellerNotif['payment_method'] ?? ''));
    }

    public function test_verify_payment_dispatches_approved_notifications_to_buyer_and_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create(['role' => 'consumer', 'name' => 'Buyer Verify']);
        $seller = User::factory()->create(['role' => 'mitra', 'name' => 'Seller Verify']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'order_source' => 'store_online',
            'total_amount' => 125000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'unpaid',
            'order_status' => 'pending_payment',
            'payment_proof_url' => 'storage/payment_proofs/orders/demo.jpg',
            'paid_amount' => 125000,
            'payment_submitted_at' => now(),
            'shipping_status' => 'pending',
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($seller)
            ->post(route('mitra.orders.markPaid', ['orderId' => $orderId]))
            ->assertRedirect();

        $buyerNotif = $this->latestPaymentNotification((int) $buyer->id, 'approved');
        $adminNotif = $this->latestPaymentNotification((int) $admin->id, 'approved');

        $this->assertNotNull($buyerNotif);
        $this->assertNotNull($adminNotif);
        $this->assertSame($orderId, (int) ($buyerNotif['order_id'] ?? 0));
        $this->assertSame('bank_transfer', (string) ($adminNotif['payment_method'] ?? ''));
    }

    public function test_submit_payment_proof_twice_does_not_duplicate_pending_notifications(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create(['role' => 'consumer', 'name' => 'Buyer Duplicate']);
        $seller = User::factory()->create(['role' => 'mitra', 'name' => 'Seller Duplicate']);
        $this->seedApprovedConsumerMode($buyer->id, 'farmer_seller');

        $productId = DB::table('store_products')->insertGetId([
            'mitra_id' => $seller->id,
            'name' => 'Benih Kedelai',
            'description' => 'Benih kedelai unggul.',
            'price' => 55000,
            'stock_qty' => 10,
            'image_url' => null,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($buyer)->post(route('cart.store'), [
            'product_id' => $productId,
            'qty' => 1,
        ])->assertRedirect();

        $this->actingAs($buyer)
            ->post(route('checkout'), [
                'payment_method' => 'bank_transfer',
            ])
            ->assertRedirect(route('orders.mine'));

        $order = DB::table('orders')->where('buyer_id', $buyer->id)->first();
        $this->assertNotNull($order);

        $this->actingAs($buyer)
            ->post(route('orders.transfer-proof', ['orderId' => $order->id]), [
                'payment_method' => 'bank_transfer',
                'paid_amount' => 55000,
                'proof' => UploadedFile::fake()->createWithContent('proof-first.jpg', 'payment-proof-1'),
            ])
            ->assertRedirect();

        $this->actingAs($buyer)
            ->post(route('orders.transfer-proof', ['orderId' => $order->id]), [
                'payment_method' => 'bank_transfer',
                'paid_amount' => 55000,
                'proof' => UploadedFile::fake()->createWithContent('proof-second.jpg', 'payment-proof-2'),
            ])
            ->assertRedirect();

        $this->assertSame(1, $this->countPaymentNotificationsByStatus((int) $buyer->id, 'pending'));
        $this->assertSame(1, $this->countPaymentNotificationsByStatus((int) $seller->id, 'pending'));
        $this->assertSame(1, $this->countPaymentNotificationsByStatus((int) $admin->id, 'pending'));
    }

    public function test_mark_shipped_dispatches_notification_to_buyer(): void
    {
        $buyer = User::factory()->create(['role' => 'consumer', 'name' => 'Buyer Shipped']);
        $seller = User::factory()->create(['role' => 'mitra', 'name' => 'Seller Shipped']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'order_source' => 'store_online',
            'total_amount' => 120000,
            'payment_method' => 'gopay',
            'payment_status' => 'paid',
            'order_status' => 'packed',
            'payment_proof_url' => null,
            'paid_amount' => 120000,
            'payment_submitted_at' => now(),
            'shipping_status' => 'pending',
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($seller)
            ->post(route('mitra.orders.markShipped', ['orderId' => $orderId]), [
                'resi_number' => 'RESI-001',
            ])
            ->assertRedirect();

        $buyerNotif = $this->latestPaymentNotification((int) $buyer->id, 'shipped');
        $this->assertNotNull($buyerNotif);
        $this->assertSame($orderId, (int) ($buyerNotif['order_id'] ?? 0));
        $this->assertSame('/orders/mine', (string) ($buyerNotif['action_url'] ?? ''));
    }

    private function latestPaymentNotification(int $userId, string $status): ?array
    {
        $rows = DB::table('notifications')
            ->where('type', PaymentOrderStatusNotification::class)
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId)
            ->orderByDesc('created_at')
            ->get(['data']);

        foreach ($rows as $row) {
            $payload = json_decode((string) $row->data, true);
            if (($payload['status'] ?? null) === $status) {
                return is_array($payload) ? $payload : null;
            }
        }

        return null;
    }

    private function countPaymentNotificationsByStatus(int $userId, string $status): int
    {
        $rows = DB::table('notifications')
            ->where('type', PaymentOrderStatusNotification::class)
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId)
            ->orderByDesc('created_at')
            ->get(['data']);

        $count = 0;
        foreach ($rows as $row) {
            $payload = json_decode((string) $row->data, true);
            if (($payload['status'] ?? null) === $status) {
                $count++;
            }
        }

        return $count;
    }

    private function seedApprovedConsumerMode(int $userId, string $mode): void
    {
        DB::table('consumer_profiles')->insert([
            'user_id' => $userId,
            'address' => null,
            'mode' => $mode,
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
