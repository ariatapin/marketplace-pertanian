<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminMarketplaceModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_marketplace_module_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.modules.marketplace'));

        $response->assertOk();
        $response->assertSee('data-testid="admin-marketplace-page"', false);
        $response->assertSee('data-testid="admin-marketplace-core-status"', false);
    }

    public function test_admin_can_open_marketplace_overview_and_content_sections(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        DB::table('mitra_applications')->insert([
            'user_id' => $admin->id,
            'full_name' => 'Mitra Demo',
            'email' => 'mitra.demo@example.test',
            'region_id' => null,
            'ktp_url' => null,
            'npwp_url' => null,
            'nib_url' => null,
            'warehouse_address' => 'Jl. Demo',
            'warehouse_lat' => null,
            'warehouse_lng' => null,
            'warehouse_building_photo_url' => null,
            'products_managed' => null,
            'warehouse_capacity' => null,
            'special_certification_url' => null,
            'status' => 'pending',
            'decided_by' => null,
            'decided_at' => null,
            'notes' => null,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('marketplace_announcements')->insert([
            'type' => 'promo',
            'title' => 'Promo Marketplace Baru',
            'message' => 'Diskon ongkir terbatas.',
            'cta_label' => 'Lihat',
            'cta_url' => '/?q=promo',
            'is_active' => true,
            'sort_order' => 1,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.modules.marketplace', [
            'section' => 'notifications',
        ]));

        $response->assertOk();
        $response->assertDontSee('data-testid="admin-marketplace-notifications"', false);
        $response->assertSeeText('Ringkasan Konten');
        $response->assertSeeText('Informasi Pengajuan Mitra');

        $contentResponse = $this->actingAs($admin)->get(route('admin.modules.marketplace', [
            'section' => 'content',
        ]));

        $contentResponse->assertOk();
        $contentResponse->assertSee('data-testid="admin-marketplace-content"', false);
        $contentResponse->assertSee('Promo Marketplace Baru');
    }

    public function test_admin_can_update_affiliate_lock_policy_from_marketplace_overview(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->post(route('admin.modules.marketplace.affiliateLockPolicy.update'), [
                'cooldown_enabled' => '1',
                'lock_days' => '14',
                'refresh_on_repromote' => '1',
            ]);

        $response->assertRedirect(route('admin.modules.marketplace', ['section' => 'overview']));
        $this->assertDatabaseHas('feature_flags', [
            'key' => 'affiliate_lock_policy',
            'is_enabled' => 1,
        ]);

        $flag = DB::table('feature_flags')
            ->where('key', 'affiliate_lock_policy')
            ->first(['description']);

        $this->assertNotNull($flag);
        $description = json_decode((string) ($flag->description ?? ''), true);
        $this->assertIsArray($description);
        $this->assertSame(14, (int) ($description['lock_days'] ?? 0));
        $this->assertTrue((bool) ($description['refresh_on_repromote'] ?? false));
    }

    public function test_admin_can_update_affiliate_commission_range_from_marketplace_overview(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->post(route('admin.modules.marketplace.affiliateCommissionRange.update'), [
                'affiliate_commission_min_percent' => 4.5,
                'affiliate_commission_max_percent' => 19.25,
            ]);

        $response->assertRedirect(route('admin.modules.marketplace', ['section' => 'overview']));
        $this->assertDatabaseHas('admin_profiles', [
            'user_id' => $admin->id,
            'affiliate_commission_min_percent' => 4.50,
            'affiliate_commission_max_percent' => 19.25,
        ]);
    }

    public function test_marketplace_tabs_render_expected_sections_after_ui_relocation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $overviewResponse = $this->actingAs($admin)->get(route('admin.modules.marketplace', [
            'section' => 'overview',
        ]));

        $overviewResponse->assertOk();
        $overviewResponse->assertSeeText('Informasi Pengajuan Mitra');
        $overviewResponse->assertSeeText('Simpan Status Pengajuan');
        $overviewResponse->assertSeeText('Simpan Batas Komisi');
        $overviewResponse->assertDontSeeText('Kelola Konten');
        $overviewResponse->assertDontSeeText('Kontrol Pengajuan Mitra');

        $contentResponse = $this->actingAs($admin)->get(route('admin.modules.marketplace', [
            'section' => 'content',
        ]));

        $contentResponse->assertOk();
        $contentResponse->assertSeeText('Manajemen Konten');
        $contentResponse->assertDontSeeText('Kontrol Pengajuan Mitra');
        $contentResponse->assertDontSeeText('Simpan Status Pengajuan');
    }
}
