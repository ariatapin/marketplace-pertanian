<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class WeatherWidgetViewModelFactory
{
    public function make(array $loc, array $current, array $alert, ?object $adminNotice): array
    {
        $severityMeta = $this->severityMeta((string) ($alert['severity'] ?? 'green'));
        $opsAction = $this->opsAction((string) ($alert['type'] ?? 'normal'));

        $validUntil = $this->parseCarbon($alert['valid_until'] ?? null);
        $validUntilLabel = $validUntil ? $validUntil->translatedFormat('d M Y, H:i') : null;

        $adminNoticeTitle = trim((string) ($adminNotice->title ?? ''));
        $adminNoticeMessage = (string) ($adminNotice->message ?? 'Belum ada notifikasi manual dari admin untuk lokasi ini.');
        $adminNoticeTime = $this->parseCarbon($adminNotice->created_at ?? null);
        $adminNoticeTimeLabel = $adminNoticeTime ? $adminNoticeTime->diffForHumans() : null;

        $temp = data_get($current, 'main.temp');
        $humidity = data_get($current, 'main.humidity');
        $wind = data_get($current, 'wind.speed');
        $sourceCode = $this->resolveWeatherSourceCode($current);

        $locationLabel = (string) ($loc['label'] ?? '-');
        $lat = $loc['lat'] ?? '-';
        $lng = $loc['lng'] ?? '-';
        $coordinatesLabel = $lat . ', ' . $lng;

        $alertMessage = (string) ($alert['message'] ?? 'Data cuaca belum tersedia.');

        return [
            'loc' => $loc,
            'alert' => $alert,
            'severityMeta' => $severityMeta,
            'opsAction' => $opsAction,
            'validUntil' => $validUntil,
            'adminNoticeTitle' => $adminNoticeTitle,
            'adminNoticeMessage' => $adminNoticeMessage,
            'adminNoticeTime' => $adminNoticeTimeLabel,
            'temp' => $temp,
            'humidity' => $humidity,
            'wind' => $wind,

            'severityLabel' => $severityMeta['label'],
            'severityBadgeClass' => $severityMeta['badge'],
            'severityPanelClass' => $severityMeta['panel'],
            'adminSyncNote' => $severityMeta['note'],
            'alertMessage' => $alertMessage,
            'validUntilLabel' => $validUntilLabel,
            'locationLabel' => $locationLabel,
            'tempLabel' => $temp !== null ? $temp . ' C' : '-',
            'humidityLabel' => $humidity !== null ? $humidity . ' %' : '-',
            'windLabel' => $wind !== null ? $wind . ' m/s' : '-',
            'adminNoticeTimeLabel' => $adminNoticeTimeLabel,
            'coordinatesLabel' => $coordinatesLabel,
            'sourceCode' => $sourceCode,
            'sourceLabel' => $this->weatherSourceLabel($sourceCode),
            'sourceBadgeClass' => $this->weatherSourceBadgeClass($sourceCode),
        ];
    }

    private function severityMeta(string $severity): array
    {
        return match ($severity) {
            'red' => [
                'label' => 'SIAGA TINGGI',
                'badge' => 'border-rose-200 bg-rose-100 text-rose-700',
                'panel' => 'border-rose-200 bg-rose-50',
                'note' => 'Prioritas tinggi. Admin disarankan kirim notifikasi segera ke user terdampak.',
            ],
            'yellow' => [
                'label' => 'WASPADA',
                'badge' => 'border-amber-200 bg-amber-100 text-amber-700',
                'panel' => 'border-amber-200 bg-amber-50',
                'note' => 'Waspada operasional. Admin dapat kirim pengingat mitigasi stok dan distribusi.',
            ],
            default => [
                'label' => 'NORMAL',
                'badge' => 'border-emerald-200 bg-emerald-100 text-emerald-700',
                'panel' => 'border-emerald-200 bg-emerald-50',
                'note' => 'Kondisi stabil. Belum perlu notifikasi massal dari admin.',
            ],
        };
    }

    private function opsAction(string $alertType): string
    {
        return match ($alertType) {
            'heavy_rain' => 'Tunda pengiriman non-prioritas, amankan stok sensitif air, dan siapkan jalur distribusi alternatif.',
            'strong_wind' => 'Periksa keamanan gudang, rak display, serta kemasan pengiriman yang rawan terhempas.',
            'heat' => 'Prioritaskan penyimpanan produk segar di area teduh dan percepat rotasi stok rentan.',
            'rain' => 'Siapkan buffer waktu distribusi 2-4 jam dan pastikan kemasan tahan lembap.',
            'wind' => 'Lakukan pengecekan berkala armada dan area bongkar muat sebelum kirim.',
            default => 'Operasional normal. Fokus pada restock rutin dan monitoring cuaca per 3 jam.',
        };
    }

    private function parseCarbon(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveWeatherSourceCode(array $current): string
    {
        $source = strtolower(trim((string) data_get($current, 'source', 'openweather')));

        return $source === 'bmkg_fallback' ? 'bmkg_fallback' : 'openweather';
    }

    private function weatherSourceLabel(string $sourceCode): string
    {
        return $sourceCode === 'bmkg_fallback' ? 'BMKG Fallback' : 'OpenWeather';
    }

    private function weatherSourceBadgeClass(string $sourceCode): string
    {
        return $sourceCode === 'bmkg_fallback'
            ? 'border-indigo-200 bg-indigo-100 text-indigo-700'
            : 'border-cyan-200 bg-cyan-100 text-cyan-700';
    }
}
