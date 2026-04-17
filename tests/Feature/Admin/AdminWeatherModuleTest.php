<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\AdminWeatherNoticeNotification;
use App\Support\BehaviorRecommendationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminWeatherModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_weather_module_with_cached_snapshots(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $provinceId = DB::table('provinces')->insertGetId([
            'name' => 'Jawa Tengah',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId,
            'name' => 'Semarang',
            'type' => 'Kota',
            'lat' => -6.9666670,
            'lng' => 110.4166640,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::factory()->create([
            'role' => 'consumer',
            'city_id' => $cityId,
        ]);

        DB::table('weather_snapshots')->insert([
            [
                'provider' => 'openweather',
                'kind' => 'current',
                'location_type' => 'city',
                'location_id' => $cityId,
                'lat' => -6.9666670,
                'lng' => 110.4166640,
                'payload' => json_encode([
                    'main' => [
                        'temp' => 29,
                        'humidity' => 80,
                    ],
                    'wind' => [
                        'speed' => 6,
                    ],
                ]),
                'fetched_at' => now(),
                'valid_until' => now()->addHour(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'provider' => 'openweather',
                'kind' => 'forecast',
                'location_type' => 'city',
                'location_id' => $cityId,
                'lat' => -6.9666670,
                'lng' => 110.4166640,
                'payload' => json_encode([
                    'list' => [
                        ['main' => ['temp' => 30], 'wind' => ['speed' => 8], 'rain' => ['3h' => 4], 'pop' => 0.6, 'dt_txt' => now()->addHours(3)->toDateTimeString()],
                        ['main' => ['temp' => 30], 'wind' => ['speed' => 8], 'rain' => ['3h' => 4], 'pop' => 0.6, 'dt_txt' => now()->addHours(6)->toDateTimeString()],
                    ],
                ]),
                'fetched_at' => now(),
                'valid_until' => now()->addHours(3),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.modules.weather'));

        $response->assertOk();
        $response->assertSee('data-testid="admin-weather-page"', false);
        $response->assertSee('data-testid="admin-weather-status-panel"', false);
        $response->assertSee('data-testid="admin-weather-snapshot-panel"', false);
        $response->assertSee('Kota Semarang');
        $response->assertSee('Waspada');
        $response->assertSee('Snapshot Cache Terbaru');
    }

    public function test_admin_can_send_weather_notice_from_weather_module(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $provinceId = DB::table('provinces')->insertGetId([
            'name' => 'Jawa Barat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId,
            'name' => 'Bandung',
            'type' => 'Kota',
            'lat' => -6.9147440,
            'lng' => 107.6098100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherCityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId,
            'name' => 'Bekasi',
            'type' => 'Kota',
            'lat' => -6.2415860,
            'lng' => 106.9924160,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $targetConsumer = User::factory()->create([
            'role' => 'consumer',
            'province_id' => $provinceId,
            'city_id' => $cityId,
        ]);

        $targetMitra = User::factory()->create([
            'role' => 'mitra',
            'province_id' => $provinceId,
            'city_id' => $cityId,
        ]);

        $outOfScopeConsumer = User::factory()->create([
            'role' => 'consumer',
            'province_id' => $provinceId,
            'city_id' => $otherCityId,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.modules.weather.notices.store'), [
                'scope' => 'city',
                'city_id' => $cityId,
                'severity' => 'red',
                'title' => 'Siaga Hujan Lebat',
                'message' => 'Tunda pengiriman non-prioritas untuk area terdampak.',
                'valid_until' => now()->addDay()->format('Y-m-d H:i:s'),
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('admin_weather_notices', [
            'scope' => 'city',
            'city_id' => $cityId,
            'province_id' => $provinceId,
            'severity' => 'red',
            'title' => 'Siaga Hujan Lebat',
        ]);

        $this->assertDatabaseHas('notifications', [
            'type' => AdminWeatherNoticeNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $targetConsumer->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'type' => AdminWeatherNoticeNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $targetMitra->id,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'type' => AdminWeatherNoticeNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $outOfScopeConsumer->id,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'type' => AdminWeatherNoticeNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $admin->id,
        ]);
    }

    public function test_admin_can_activate_notice_and_dispatch_weather_notification(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $provinceId = DB::table('provinces')->insertGetId([
            'name' => 'Bali',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId,
            'name' => 'Denpasar',
            'type' => 'Kota',
            'lat' => -8.6704580,
            'lng' => 115.2126290,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $targetConsumer = User::factory()->create([
            'role' => 'consumer',
            'province_id' => $provinceId,
            'city_id' => $cityId,
        ]);

        $noticeId = DB::table('admin_weather_notices')->insertGetId([
            'scope' => 'city',
            'province_id' => $provinceId,
            'city_id' => $cityId,
            'district_id' => null,
            'severity' => 'red',
            'title' => 'Alert Aktivasi',
            'message' => 'Peringatan aktif untuk area ini.',
            'valid_until' => now()->addHours(5),
            'is_active' => false,
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.modules.weather.notices.toggle', ['noticeId' => $noticeId]))
            ->assertRedirect();

        $this->assertDatabaseHas('admin_weather_notices', [
            'id' => $noticeId,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('notifications', [
            'type' => AdminWeatherNoticeNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $targetConsumer->id,
        ]);
    }

    public function test_auto_mitra_notice_activation_dispatches_only_to_mitra(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $provinceId = DB::table('provinces')->insertGetId([
            'name' => 'Banten',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId,
            'name' => 'Serang',
            'type' => 'Kota',
            'lat' => -6.1200900,
            'lng' => 106.1502800,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $targetMitra = User::factory()->create([
            'role' => 'mitra',
            'province_id' => $provinceId,
            'city_id' => $cityId,
        ]);

        $nonTargetConsumer = User::factory()->create([
            'role' => 'consumer',
            'province_id' => $provinceId,
            'city_id' => $cityId,
        ]);

        $noticeId = DB::table('admin_weather_notices')->insertGetId([
            'scope' => 'city',
            'province_id' => $provinceId,
            'city_id' => $cityId,
            'district_id' => null,
            'severity' => 'yellow',
            'title' => '[AUTO][MITRA] Alert Cuaca',
            'message' => 'Notifikasi otomatis khusus mitra.',
            'valid_until' => now()->addHours(5),
            'is_active' => false,
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.modules.weather.notices.toggle', ['noticeId' => $noticeId]))
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'type' => AdminWeatherNoticeNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $targetMitra->id,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'type' => AdminWeatherNoticeNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $nonTargetConsumer->id,
        ]);
    }

    public function test_inactive_weather_notice_does_not_dispatch_notification(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $provinceId = DB::table('provinces')->insertGetId([
            'name' => 'Lampung',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId,
            'name' => 'Bandar Lampung',
            'type' => 'Kota',
            'lat' => -5.3971400,
            'lng' => 105.2667920,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $targetConsumer = User::factory()->create([
            'role' => 'consumer',
            'province_id' => $provinceId,
            'city_id' => $cityId,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.modules.weather.notices.store'), [
                'scope' => 'city',
                'city_id' => $cityId,
                'severity' => 'yellow',
                'title' => 'Notice Draft',
                'message' => 'Ini masih nonaktif.',
                'valid_until' => now()->addDay()->format('Y-m-d H:i:s'),
                'is_active' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('notifications', [
            'type' => AdminWeatherNoticeNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $targetConsumer->id,
        ]);
    }

    public function test_updating_notice_with_same_payload_does_not_duplicate_notification(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $provinceId = DB::table('provinces')->insertGetId([
            'name' => 'Nusa Tenggara Barat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId,
            'name' => 'Mataram',
            'type' => 'Kota',
            'lat' => -8.5833300,
            'lng' => 116.1166690,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $targetConsumer = User::factory()->create([
            'role' => 'consumer',
            'province_id' => $provinceId,
            'city_id' => $cityId,
        ]);

        $payload = [
            'scope' => 'city',
            'city_id' => $cityId,
            'severity' => 'red',
            'title' => 'Alert Cuaca Sama',
            'message' => 'Pesan identik untuk menguji dedup.',
            'valid_until' => now()->addDay()->format('Y-m-d H:i:s'),
            'is_active' => 1,
        ];

        $this->actingAs($admin)
            ->post(route('admin.modules.weather.notices.store'), $payload)
            ->assertRedirect();

        $noticeId = (int) DB::table('admin_weather_notices')
            ->where('title', 'Alert Cuaca Sama')
            ->value('id');

        $firstCount = (int) DB::table('notifications')
            ->where('type', AdminWeatherNoticeNotification::class)
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $targetConsumer->id)
            ->count();

        $this->assertSame(1, $firstCount);

        $this->actingAs($admin)
            ->patch(route('admin.modules.weather.notices.update', ['noticeId' => $noticeId]), $payload)
            ->assertRedirect();

        $secondCount = (int) DB::table('notifications')
            ->where('type', AdminWeatherNoticeNotification::class)
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $targetConsumer->id)
            ->count();

        $this->assertSame(1, $secondCount);
    }

    public function test_updating_notice_with_changed_payload_dispatches_new_notification(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $provinceId = DB::table('provinces')->insertGetId([
            'name' => 'Sumatera Barat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId,
            'name' => 'Padang',
            'type' => 'Kota',
            'lat' => -0.9470830,
            'lng' => 100.4171830,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $targetConsumer = User::factory()->create([
            'role' => 'consumer',
            'province_id' => $provinceId,
            'city_id' => $cityId,
        ]);

        $createPayload = [
            'scope' => 'city',
            'city_id' => $cityId,
            'severity' => 'yellow',
            'title' => 'Notice Awal',
            'message' => 'Pesan awal.',
            'valid_until' => now()->addDay()->format('Y-m-d H:i:s'),
            'is_active' => 1,
        ];

        $this->actingAs($admin)
            ->post(route('admin.modules.weather.notices.store'), $createPayload)
            ->assertRedirect();

        $noticeId = (int) DB::table('admin_weather_notices')
            ->where('title', 'Notice Awal')
            ->value('id');

        $updatePayload = [
            'scope' => 'city',
            'city_id' => $cityId,
            'severity' => 'red',
            'title' => 'Notice Perubahan',
            'message' => 'Pesan diperbarui agar kirim notifikasi baru.',
            'valid_until' => now()->addDay()->format('Y-m-d H:i:s'),
            'is_active' => 1,
        ];

        $this->actingAs($admin)
            ->patch(route('admin.modules.weather.notices.update', ['noticeId' => $noticeId]), $updatePayload)
            ->assertRedirect();

        $count = (int) DB::table('notifications')
            ->where('type', AdminWeatherNoticeNotification::class)
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $targetConsumer->id)
            ->count();

        $this->assertSame(2, $count);
    }

    public function test_admin_can_update_toggle_and_delete_weather_notice(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $provinceId = DB::table('provinces')->insertGetId([
            'name' => 'Jawa Timur',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId,
            'name' => 'Surabaya',
            'type' => 'Kota',
            'lat' => -7.2504450,
            'lng' => 112.7688450,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $noticeId = DB::table('admin_weather_notices')->insertGetId([
            'scope' => 'city',
            'province_id' => $provinceId,
            'city_id' => $cityId,
            'district_id' => null,
            'severity' => 'yellow',
            'title' => 'Info Lama',
            'message' => 'Pesan lama',
            'valid_until' => now()->addDay(),
            'is_active' => true,
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.modules.weather.notices.update', ['noticeId' => $noticeId]), [
                'scope' => 'province',
                'province_id' => $provinceId,
                'severity' => 'red',
                'title' => 'Info Baru',
                'message' => 'Pesan baru untuk operasional.',
                'valid_until' => now()->addHours(12)->format('Y-m-d H:i:s'),
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('admin_weather_notices', [
            'id' => $noticeId,
            'scope' => 'province',
            'province_id' => $provinceId,
            'city_id' => null,
            'severity' => 'red',
            'title' => 'Info Baru',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.modules.weather.notices.toggle', ['noticeId' => $noticeId]))
            ->assertRedirect();

        $this->assertDatabaseHas('admin_weather_notices', [
            'id' => $noticeId,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.modules.weather.notices.destroy', ['noticeId' => $noticeId]))
            ->assertRedirect();

        $this->assertDatabaseMissing('admin_weather_notices', [
            'id' => $noticeId,
        ]);
    }

    public function test_admin_can_filter_notice_history_by_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        DB::table('admin_weather_notices')->insert([
            [
                'scope' => 'global',
                'province_id' => null,
                'city_id' => null,
                'district_id' => null,
                'severity' => 'yellow',
                'title' => 'Notice Active',
                'message' => 'Pesan aktif.',
                'valid_until' => now()->addDay(),
                'is_active' => true,
                'created_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'scope' => 'global',
                'province_id' => null,
                'city_id' => null,
                'district_id' => null,
                'severity' => 'green',
                'title' => 'Notice Inactive',
                'message' => 'Pesan nonaktif.',
                'valid_until' => now()->addDay(),
                'is_active' => false,
                'created_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.modules.weather', [
            'notice_status' => 'inactive',
        ]));

        $response->assertOk();
        $response->assertSee('Notice Inactive');
        $response->assertDontSee('Notice Active');
    }

    public function test_admin_can_filter_notice_history_by_location_and_keyword(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $jabarId = DB::table('provinces')->insertGetId([
            'name' => 'Jawa Barat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $jatengId = DB::table('provinces')->insertGetId([
            'name' => 'Jawa Tengah',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $bandungId = DB::table('cities')->insertGetId([
            'province_id' => $jabarId,
            'name' => 'Bandung',
            'type' => 'Kota',
            'lat' => -6.9147440,
            'lng' => 107.6098100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $semarangId = DB::table('cities')->insertGetId([
            'province_id' => $jatengId,
            'name' => 'Semarang',
            'type' => 'Kota',
            'lat' => -6.9666670,
            'lng' => 110.4166640,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_weather_notices')->insert([
            [
                'scope' => 'city',
                'province_id' => $jabarId,
                'city_id' => $bandungId,
                'district_id' => null,
                'severity' => 'red',
                'title' => 'Siaga Hujan Bandung',
                'message' => 'Prioritaskan perlindungan logistik.',
                'valid_until' => now()->addDay(),
                'is_active' => true,
                'created_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'scope' => 'city',
                'province_id' => $jatengId,
                'city_id' => $semarangId,
                'district_id' => null,
                'severity' => 'yellow',
                'title' => 'Info Cuaca Semarang',
                'message' => 'Monitoring distribusi rutin.',
                'valid_until' => now()->addDay(),
                'is_active' => true,
                'created_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.modules.weather', [
            'notice_province_id' => $jabarId,
            'notice_city_id' => $bandungId,
            'notice_q' => 'hujan bandung',
        ]));

        $response->assertOk();
        $response->assertSee('Siaga Hujan Bandung');
        $response->assertDontSee('Info Cuaca Semarang');
    }

    public function test_admin_can_deactivate_all_expired_notices(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        DB::table('admin_weather_notices')->insert([
            [
                'scope' => 'global',
                'province_id' => null,
                'city_id' => null,
                'district_id' => null,
                'severity' => 'yellow',
                'title' => 'Expired Active',
                'message' => 'Sudah lewat masa aktif.',
                'valid_until' => now()->subHour(),
                'is_active' => true,
                'created_by' => $admin->id,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ],
            [
                'scope' => 'global',
                'province_id' => null,
                'city_id' => null,
                'district_id' => null,
                'severity' => 'green',
                'title' => 'Future Active',
                'message' => 'Masih aktif.',
                'valid_until' => now()->addHours(2),
                'is_active' => true,
                'created_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.modules.weather.notices.deactivateExpired'))
            ->assertRedirect();

        $this->assertDatabaseHas('admin_weather_notices', [
            'title' => 'Expired Active',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('admin_weather_notices', [
            'title' => 'Future Active',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_prune_old_inactive_notices(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        DB::table('admin_weather_notices')->insert([
            [
                'scope' => 'global',
                'province_id' => null,
                'city_id' => null,
                'district_id' => null,
                'severity' => 'yellow',
                'title' => 'Old Inactive',
                'message' => 'Harus dihapus.',
                'valid_until' => now()->subDays(120),
                'is_active' => false,
                'created_by' => $admin->id,
                'created_at' => now()->subDays(120),
                'updated_at' => now()->subDays(120),
            ],
            [
                'scope' => 'global',
                'province_id' => null,
                'city_id' => null,
                'district_id' => null,
                'severity' => 'yellow',
                'title' => 'Recent Inactive',
                'message' => 'Belum boleh dihapus.',
                'valid_until' => now()->subDays(3),
                'is_active' => false,
                'created_by' => $admin->id,
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ],
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.modules.weather.notices.pruneInactive'), [
                'days' => 90,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('admin_weather_notices', [
            'title' => 'Old Inactive',
        ]);

        $this->assertDatabaseHas('admin_weather_notices', [
            'title' => 'Recent Inactive',
        ]);
    }

    public function test_admin_can_filter_automation_notification_for_seller_target(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sellerRecipient = User::factory()->create(['role' => 'consumer']);
        $mitraRecipient = User::factory()->create(['role' => 'mitra']);

        DB::table('notifications')->insert([
            [
                'id' => (string) Str::uuid(),
                'type' => BehaviorRecommendationNotification::class,
                'notifiable_type' => User::class,
                'notifiable_id' => $sellerRecipient->id,
                'data' => json_encode([
                    'category' => 'behavior_recommendation',
                    'status' => 'green',
                    'title' => 'Seller Forecast Test',
                    'message' => 'Potensi demand seller meningkat.',
                    'role_target' => 'seller',
                    'rule_key' => 'seller_demand_harvest_ops',
                    'target_label' => 'Kota Test Seller',
                    'dispatch_key' => 'dispatch-seller-test',
                    'action_label' => 'Buka Seller',
                    'action_url' => '/seller/dashboard',
                    'sent_at' => now()->toDateTimeString(),
                ], JSON_UNESCAPED_UNICODE),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'type' => BehaviorRecommendationNotification::class,
                'notifiable_type' => User::class,
                'notifiable_id' => $mitraRecipient->id,
                'data' => json_encode([
                    'category' => 'behavior_recommendation',
                    'status' => 'yellow',
                    'title' => 'Mitra Forecast Test',
                    'message' => 'Potensi demand mitra meningkat.',
                    'role_target' => 'mitra',
                    'rule_key' => 'mitra_demand_forecast_pesticide',
                    'target_label' => 'Kota Test Mitra',
                    'dispatch_key' => 'dispatch-mitra-test',
                    'action_label' => 'Buka Mitra',
                    'action_url' => '/mitra/dashboard',
                    'sent_at' => now()->toDateTimeString(),
                ], JSON_UNESCAPED_UNICODE),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.modules.weather', [
            'panel' => 'automation',
            'automation_role_target' => 'seller',
        ]));

        $response->assertOk();
        $response->assertSee('Otomatisasi Notifikasi');
        $response->assertSee('Seller Forecast Test');
        $response->assertDontSee('Mitra Forecast Test');
    }
}
