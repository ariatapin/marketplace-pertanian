<?php

namespace Tests\Unit;

use App\Support\WeatherWidgetViewModelFactory;
use PHPUnit\Framework\TestCase;

class WeatherWidgetViewModelFactoryTest extends TestCase
{
    public function test_it_builds_red_severity_weather_view_model(): void
    {
        $factory = new WeatherWidgetViewModelFactory();

        $result = $factory->make(
            loc: [
                'label' => 'Jakarta',
                'lat' => -6.2,
                'lng' => 106.8166667,
            ],
            current: [
                'main' => [
                    'temp' => 28.44,
                    'humidity' => 75,
                ],
                'wind' => [
                    'speed' => 1.03,
                ],
            ],
            alert: [
                'severity' => 'red',
                'type' => 'heavy_rain',
                'message' => 'Waspada hujan lebat dalam 24 jam ke depan.',
                'valid_until' => '2026-02-15 12:00:00',
            ],
            adminNotice: (object) [
                'title' => 'SIAGA BANJIR',
                'message' => 'Prioritaskan pengamanan stok di gudang rendah.',
                'created_at' => '2026-02-14 10:00:00',
            ]
        );

        $this->assertSame('SIAGA TINGGI', $result['severityLabel']);
        $this->assertSame('Waspada hujan lebat dalam 24 jam ke depan.', $result['alertMessage']);
        $this->assertSame('28.44 C', $result['tempLabel']);
        $this->assertSame('75 %', $result['humidityLabel']);
        $this->assertSame('1.03 m/s', $result['windLabel']);
        $this->assertStringContainsString('2026', (string) $result['validUntilLabel']);
        $this->assertSame('SIAGA BANJIR', $result['adminNoticeTitle']);
        $this->assertSame('Prioritaskan pengamanan stok di gudang rendah.', $result['adminNoticeMessage']);
        $this->assertNotEmpty($result['adminNoticeTimeLabel']);
    }

    public function test_it_falls_back_to_defaults_when_payload_is_empty(): void
    {
        $factory = new WeatherWidgetViewModelFactory();

        $result = $factory->make(
            loc: [],
            current: [],
            alert: [],
            adminNotice: null
        );

        $this->assertSame('NORMAL', $result['severityLabel']);
        $this->assertSame('Data cuaca belum tersedia.', $result['alertMessage']);
        $this->assertNull($result['validUntilLabel']);
        $this->assertSame('-', $result['tempLabel']);
        $this->assertSame('-', $result['humidityLabel']);
        $this->assertSame('-', $result['windLabel']);
        $this->assertSame('Belum ada notifikasi manual dari admin untuk lokasi ini.', $result['adminNoticeMessage']);
        $this->assertNull($result['adminNoticeTimeLabel']);
    }
}
