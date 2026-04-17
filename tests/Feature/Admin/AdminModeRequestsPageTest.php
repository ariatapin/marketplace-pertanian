<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminModeRequestsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_mode_requests_page_and_see_rows(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = User::factory()->create([
            'role' => 'consumer',
            'email' => 'mode.page@example.test',
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

        $response = $this->actingAs($admin)->get(route('admin.modeRequests.index'));

        $response->assertOk();
        $response->assertSee('data-testid="admin-mode-requests-page"', false);
        $response->assertSee('mode.page@example.test');
    }

    public function test_admin_can_filter_mode_requests_page_by_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $pendingUser = User::factory()->create([
            'role' => 'consumer',
            'email' => 'pending.mode@example.test',
        ]);

        $approvedUser = User::factory()->create([
            'role' => 'consumer',
            'email' => 'approved.mode@example.test',
        ]);

        DB::table('consumer_profiles')->insert([
            [
                'user_id' => $pendingUser->id,
                'address' => null,
                'mode' => 'buyer',
                'mode_status' => 'pending',
                'requested_mode' => 'farmer_seller',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $approvedUser->id,
                'address' => null,
                'mode' => 'affiliate',
                'mode_status' => 'approved',
                'requested_mode' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.modeRequests.index', ['status' => 'pending']));

        $response->assertOk();
        $response->assertSee('pending.mode@example.test');
        $response->assertDontSee('approved.mode@example.test');
    }

    public function test_admin_mode_requests_page_shows_pending_mitra_application_rows(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = User::factory()->create([
            'role' => 'consumer',
            'email' => 'mitra.review@example.test',
            'name' => 'Mitra Review',
        ]);

        DB::table('mitra_applications')->insert([
            'user_id' => $consumer->id,
            'full_name' => 'Mitra Review',
            'email' => $consumer->email,
            'region_id' => 1101,
            'ktp_url' => 'uploads/test/ktp.pdf',
            'npwp_url' => 'uploads/test/npwp.pdf',
            'nib_url' => 'uploads/test/nib.pdf',
            'warehouse_address' => 'Gudang Review',
            'warehouse_lat' => -7.81,
            'warehouse_lng' => 110.36,
            'warehouse_building_photo_url' => 'uploads/test/gudang.jpg',
            'products_managed' => 'Pupuk',
            'warehouse_capacity' => 3000,
            'special_certification_url' => null,
            'status' => 'pending',
            'submitted_at' => now(),
            'decided_by' => null,
            'decided_at' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.modeRequests.index', [
            'mitra_status' => 'pending',
        ]));

        $response->assertOk();
        $response->assertSee('data-testid="admin-mode-requests-mitra-section"', false);
        $response->assertSee('mitra.review@example.test');
    }
}
