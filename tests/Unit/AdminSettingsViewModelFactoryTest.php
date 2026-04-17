<?php

namespace Tests\Unit;

use App\Support\AdminSettingsViewModelFactory;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class AdminSettingsViewModelFactoryTest extends TestCase
{
    public function test_it_maps_mitra_flag_and_announcements(): void
    {
        $factory = new AdminSettingsViewModelFactory();

        $result = $factory->make(
            mitraFlag: [
                'is_enabled' => true,
                'description' => 'Pengajuan mitra dibuka.',
            ],
            announcements: new Collection([
                (object) [
                    'id' => 7,
                    'type' => 'promo',
                    'title' => 'Promo Panen',
                    'message' => 'Diskon khusus panen raya.',
                    'cta_label' => 'Lihat Promo',
                    'cta_url' => '/promo',
                    'sort_order' => 1,
                    'is_active' => true,
                    'starts_at' => '2026-02-15 10:00:00',
                    'ends_at' => '2026-02-20 18:30:00',
                ],
                (object) [
                    'id' => 8,
                    'type' => 'info',
                    'title' => 'Info Gudang',
                    'message' => 'Penyesuaian jadwal gudang.',
                    'cta_label' => '',
                    'cta_url' => '',
                    'sort_order' => 2,
                    'is_active' => false,
                    'starts_at' => '',
                    'ends_at' => null,
                ],
            ])
        );

        $this->assertTrue($result['mitraFlag']['is_enabled']);
        $this->assertSame('OPEN', $result['mitraFlag']['status_label']);
        $this->assertStringContainsString('emerald', $result['mitraFlag']['status_class']);

        $first = $result['announcementRows'][0];
        $this->assertSame('promo', $first['type']);
        $this->assertSame('Active', $first['status_label']);
        $this->assertStringContainsString('amber', $first['type_badge_class']);
        $this->assertSame('2026-02-15T10:00', $first['starts_at_input']);
        $this->assertSame('2026-02-20T18:30', $first['ends_at_input']);

        $second = $result['announcementRows'][1];
        $this->assertSame('Nonaktif', $second['status_label']);
        $this->assertSame('', $second['starts_at_input']);
        $this->assertSame('', $second['ends_at_input']);
    }
}
