<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_settings_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.settings'));

        $response->assertRedirect(route('admin.modules.marketplace', ['section' => 'content']));

        $contentResponse = $this->actingAs($admin)->get(route('admin.modules.marketplace', ['section' => 'content']));
        $contentResponse->assertOk();
        $contentResponse->assertSee('data-testid="admin-marketplace-content"', false);
        $contentResponse->assertSeeText('Manajemen Konten');
    }

    public function test_admin_can_update_mitra_flag_and_create_landing_announcement(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.settings.mitraSubmission.update'), [
                'is_enabled' => 1,
                'description' => 'Pengajuan mitra dibuka minggu ini.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('feature_flags', [
            'key' => 'accept_mitra',
            'is_enabled' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.settings.announcements.store'), [
                'type' => 'promo',
                'title' => 'Promo Pupuk Organik',
                'message' => 'Diskon ongkir untuk pembelian stok minggu ini.',
                'cta_label' => 'Lihat Promo',
                'cta_url' => '/?q=pupuk',
                'sort_order' => 1,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('marketplace_announcements', [
            'type' => 'promo',
            'title' => 'Promo Pupuk Organik',
            'is_active' => true,
        ]);

        $this->post(route('logout'));
        $landingResponse = $this->get(route('landing'));

        $landingResponse->assertOk();
        $landingResponse->assertSee('Pengajuan Mitra Pengadaan Admin');
        $landingResponse->assertSee('DIBUKA');
        $landingResponse->assertSee('Promo Pupuk Organik');
    }

    public function test_admin_can_toggle_role_automation_flag(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.settings.automation.update'), [
                'automation_enabled' => 1,
                'automation_description' => 'Aktifkan auto role cycle.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('feature_flags', [
            'key' => 'automation_role_cycle',
            'is_enabled' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.settings.automation.update'), [
                'automation_enabled' => 0,
                'automation_description' => 'Dinonaktifkan saat maintenance.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('feature_flags', [
            'key' => 'automation_role_cycle',
            'is_enabled' => false,
            'description' => 'Dinonaktifkan saat maintenance.',
        ]);
    }

    public function test_admin_can_upload_announcement_image_and_landing_renders_it(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $png1x1 = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgQf7z0sAAAAASUVORK5CYII='
        );

        $this->actingAs($admin)
            ->post(route('admin.settings.announcements.store'), [
                'type' => 'banner',
                'title' => 'Banner Musim Hujan',
                'message' => 'Siapkan stok tambahan saat curah hujan naik.',
                'cta_label' => 'Cek Stok',
                'cta_url' => '/?q=stok',
                'sort_order' => 2,
                'is_active' => 1,
                'image' => UploadedFile::fake()->createWithContent('banner-hujan.png', $png1x1 ?: 'png'),
            ])
            ->assertRedirect();

        $announcement = DB::table('marketplace_announcements')
            ->where('title', 'Banner Musim Hujan')
            ->first(['id', 'image_url']);

        $this->assertNotNull($announcement);
        $this->assertNotEmpty((string) ($announcement->image_url ?? ''));
        Storage::disk('public')->assertExists((string) $announcement->image_url);

        $this->post(route('logout'));
        $landingResponse = $this->get(route('landing'));
        $landingResponse->assertOk();
        $landingResponse->assertSee('Banner Musim Hujan');
        $landingResponse->assertSee('/storage/' . ltrim((string) $announcement->image_url, '/'));
    }
}
