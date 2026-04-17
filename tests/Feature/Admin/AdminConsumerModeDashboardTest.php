<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminConsumerModeDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_farmer_seller_request_is_visible_in_admin_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $consumer = User::factory()->create([
            'role' => 'consumer',
            'email' => 'buyer.pending@example.test',
        ]);

        DB::table('feature_flags')->insert([
            'key' => 'accept_mitra',
            'is_enabled' => true,
            'description' => 'Pengajuan mitra dibuka.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($consumer)
            ->post(route('profile.requestFarmerSeller'))
            ->assertRedirect();

        $this->actingAs($admin);

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Pengajuan Aktivasi Consumer');
        $response->assertSee('buyer.pending@example.test');
        $response->assertSee('farmer seller');
    }

    public function test_admin_can_approve_consumer_mode_request_from_dashboard_action(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        DB::table('consumer_profiles')->insert([
            'user_id' => $consumer->id,
            'address' => null,
            'mode' => 'buyer',
            'mode_status' => 'pending',
            'requested_mode' => 'farmer_seller',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.mode.approve', ['userId' => $consumer->id]), [
                'mode' => 'farmer_seller',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('consumer_profiles', [
            'user_id' => $consumer->id,
            'mode' => 'farmer_seller',
            'mode_status' => 'approved',
            'requested_mode' => null,
        ]);
    }

    public function test_admin_can_reject_consumer_mode_request_from_dashboard_action(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        DB::table('consumer_profiles')->insert([
            'user_id' => $consumer->id,
            'address' => null,
            'mode' => 'buyer',
            'mode_status' => 'pending',
            'requested_mode' => 'affiliate',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.mode.reject', ['userId' => $consumer->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('consumer_profiles', [
            'user_id' => $consumer->id,
            'mode' => 'buyer',
            'mode_status' => 'rejected',
            'requested_mode' => null,
        ]);
    }

    public function test_admin_dashboard_shows_procurement_notification_shortcut_in_topbar(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin);

        $response = $this->get(route('admin.dashboard'));

        $shortcutUrl = route('admin.modules.procurement', [
            'section' => 'orders',
            'status' => 'pending',
        ]) . '#order-mitra';

        $response->assertOk();
        $response->assertSee('Notifikasi Pengadaan');
        $response->assertSee(str_replace('&', '&amp;', $shortcutUrl), false);
    }

    public function test_admin_dashboard_shows_procurement_notification_badge_with_pending_count(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $mitra = User::factory()->create([
            'role' => 'mitra',
        ]);

        DB::table('admin_orders')->insert([
            [
                'mitra_id' => $mitra->id,
                'total_amount' => 120000,
                'status' => 'pending',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'mitra_id' => $mitra->id,
                'total_amount' => 90000,
                'status' => 'approved',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('data-testid="admin-procurement-notification-badge"', false);
        $response->assertSee('data-pending-count="1"', false);
    }

    public function test_admin_dashboard_hides_procurement_notification_badge_when_no_pending_orders(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $mitra = User::factory()->create([
            'role' => 'mitra',
        ]);

        DB::table('admin_orders')->insert([
            'mitra_id' => $mitra->id,
            'total_amount' => 120000,
            'status' => 'approved',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('data-testid="admin-procurement-notification-badge"', false);
    }
}
