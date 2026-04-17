<?php

namespace Tests\Feature\Consumer;

use App\Models\User;
use App\Support\AdminWeatherNoticeNotification;
use App\Support\BehaviorRecommendationNotification;
use App\Support\MitraApplicationStatusNotification;
use App\Support\PaymentOrderStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_open_notification_center_with_unread_filter(): void
    {
        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        $this->createNotification(
            $consumer,
            [
                'status' => 'pending',
                'title' => 'Pending ditinjau',
                'message' => 'Pengajuan Anda sedang ditinjau admin.',
            ]
        );

        $this->createNotification(
            $consumer,
            [
                'status' => 'approved',
                'title' => 'Sudah selesai',
                'message' => 'Pengajuan sudah disetujui.',
            ],
            now()->subMinutes(5)
        );

        $response = $this->actingAs($consumer)
            ->get(route('notifications.index', ['status' => 'unread']));

        $response->assertOk();
        $response->assertSee('Notifikasi Akun');
        $response->assertSee('Pending ditinjau');
        $response->assertDontSee('Sudah selesai');
    }

    public function test_user_can_mark_single_notification_as_read(): void
    {
        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        $notificationId = $this->createNotification(
            $consumer,
            [
                'status' => 'pending',
                'title' => 'Perlu perhatian',
                'message' => 'Segera cek status terbaru.',
            ]
        );

        $response = $this->actingAs($consumer)
            ->post(route('notifications.read', $notificationId), [
                'status' => 'unread',
            ]);

        $response->assertRedirect(route('notifications.index', ['status' => 'unread']));

        $this->assertDatabaseMissing('notifications', [
            'id' => $notificationId,
            'read_at' => null,
        ]);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        $this->createNotification(
            $consumer,
            [
                'status' => 'pending',
                'title' => 'Notif 1',
                'message' => 'Pesan notifikasi 1',
            ]
        );

        $this->createNotification(
            $consumer,
            [
                'status' => 'approved',
                'title' => 'Notif 2',
                'message' => 'Pesan notifikasi 2',
            ]
        );

        $response = $this->actingAs($consumer)
            ->post(route('notifications.readAll'), [
                'status' => 'all',
            ]);

        $response->assertRedirect(route('notifications.index', ['status' => 'all']));

        $this->assertSame(0, DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $consumer->id)
            ->whereNull('read_at')
            ->count());
    }

    public function test_user_cannot_mark_other_user_notification_as_read(): void
    {
        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);
        $otherConsumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        $foreignNotificationId = $this->createNotification(
            $otherConsumer,
            [
                'status' => 'pending',
                'title' => 'Notif pengguna lain',
                'message' => 'Tidak boleh bisa ditandai user lain.',
            ]
        );

        $response = $this->actingAs($consumer)
            ->post(route('notifications.read', $foreignNotificationId));

        $response->assertRedirect(route('notifications.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('notifications', [
            'id' => $foreignNotificationId,
            'read_at' => null,
        ]);
    }

    public function test_weather_notification_is_visible_in_notification_center(): void
    {
        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        $notificationId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $notificationId,
            'type' => AdminWeatherNoticeNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $consumer->id,
            'data' => json_encode([
                'status' => 'red',
                'title' => 'Siaga Tinggi Cuaca',
                'message' => 'Hujan lebat diprediksi terjadi dalam 24 jam.',
                'scope' => 'city',
                'target_label' => 'Kota Bandung, Jawa Barat',
                'valid_until' => now()->addHours(12)->format('Y-m-d H:i:s'),
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($consumer)
            ->get(route('notifications.index'));

        $response->assertOk();
        $response->assertSee('Cuaca & Lokasi');
        $response->assertSee('Siaga Tinggi Cuaca');
        $response->assertSee('Target: Kota Bandung, Jawa Barat');
    }

    public function test_payment_notification_is_visible_in_notification_center(): void
    {
        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        $notificationId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $notificationId,
            'type' => PaymentOrderStatusNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $consumer->id,
            'data' => json_encode([
                'status' => 'approved',
                'title' => 'Pembayaran order #101 terverifikasi',
                'message' => 'Seller memverifikasi pembayaran Anda.',
                'action_url' => route('orders.mine'),
                'action_label' => 'Pantau Pesanan',
                'order_id' => 101,
                'payment_method' => 'gopay',
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($consumer)
            ->get(route('notifications.index'));

        $response->assertOk();
        $response->assertSee('Pembayaran Order');
        $response->assertSee('Pembayaran order #101 terverifikasi');
    }

    public function test_user_can_filter_notification_by_payment_type(): void
    {
        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        $this->createNotification(
            $consumer,
            [
                'status' => 'pending',
                'title' => 'Pengajuan mitra menunggu',
                'message' => 'Admin sedang memverifikasi pengajuan mitra.',
            ]
        );

        $this->createNotification(
            $consumer,
            [
                'status' => 'pending',
                'title' => 'Pembayaran order #202 pending',
                'message' => 'Upload bukti transfer sudah diterima.',
            ],
            null,
            PaymentOrderStatusNotification::class
        );

        $this->createNotification(
            $consumer,
            [
                'status' => 'red',
                'title' => 'Siaga hujan',
                'message' => 'Waspada hujan lebat di wilayah Anda.',
            ],
            null,
            AdminWeatherNoticeNotification::class
        );

        $response = $this->actingAs($consumer)
            ->get(route('notifications.index', [
                'status' => 'all',
                'type' => 'payment',
            ]));

        $response->assertOk();
        $response->assertSee('Pembayaran order #202 pending');
        $response->assertDontSee('Pengajuan mitra menunggu');
        $response->assertDontSee('Siaga hujan');
    }

    public function test_user_can_mark_single_notification_read_and_keep_filters(): void
    {
        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        $notificationId = $this->createNotification(
            $consumer,
            [
                'status' => 'pending',
                'title' => 'Pembayaran order #301 pending',
                'message' => 'Pembayaran masih menunggu verifikasi seller.',
            ],
            null,
            PaymentOrderStatusNotification::class
        );

        $response = $this->actingAs($consumer)
            ->post(route('notifications.read', $notificationId), [
                'status' => 'unread',
                'type' => 'payment',
            ]);

        $response->assertRedirect(route('notifications.index', [
            'status' => 'unread',
            'type' => 'payment',
        ]));

        $this->assertDatabaseMissing('notifications', [
            'id' => $notificationId,
            'read_at' => null,
        ]);
    }

    public function test_user_can_mark_all_notifications_read_only_for_selected_type(): void
    {
        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        $paymentNotificationId = $this->createNotification(
            $consumer,
            [
                'status' => 'pending',
                'title' => 'Pembayaran order #401 pending',
                'message' => 'Pembayaran sedang diverifikasi.',
            ],
            null,
            PaymentOrderStatusNotification::class
        );

        $mitraNotificationId = $this->createNotification(
            $consumer,
            [
                'status' => 'pending',
                'title' => 'Mitra menunggu approval',
                'message' => 'Pengajuan mitra masih dalam antrean.',
            ],
            null,
            MitraApplicationStatusNotification::class
        );

        $response = $this->actingAs($consumer)
            ->post(route('notifications.readAll'), [
                'status' => 'all',
                'type' => 'payment',
            ]);

        $response->assertRedirect(route('notifications.index', [
            'status' => 'all',
            'type' => 'payment',
        ]));

        $this->assertDatabaseMissing('notifications', [
            'id' => $paymentNotificationId,
            'read_at' => null,
        ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $mitraNotificationId,
            'read_at' => null,
        ]);
    }

    public function test_user_can_filter_notification_by_recommendation_type(): void
    {
        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        $this->createNotification(
            $consumer,
            [
                'status' => 'yellow',
                'title' => 'Potensi Permintaan Pestisida',
                'message' => 'Prediksi permintaan naik dalam 7-10 hari ke depan.',
            ],
            null,
            BehaviorRecommendationNotification::class
        );

        $this->createNotification(
            $consumer,
            [
                'status' => 'red',
                'title' => 'Siaga hujan',
                'message' => 'Waspada hujan lebat di wilayah Anda.',
            ],
            null,
            AdminWeatherNoticeNotification::class
        );

        $response = $this->actingAs($consumer)
            ->get(route('notifications.index', [
                'status' => 'all',
                'type' => 'recommendation',
            ]));

        $response->assertOk();
        $response->assertSee('Potensi Permintaan Pestisida');
        $response->assertDontSee('Siaga hujan');
    }

    private function createNotification(
        User $user,
        array $payload,
        $readAt = null,
        string $type = MitraApplicationStatusNotification::class
    ): string
    {
        $notificationId = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $notificationId,
            'type' => $type,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode($payload),
            'read_at' => $readAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $notificationId;
    }
}
