<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class AdminWeatherViewModelFactory
{
    public function make(
        array $summary,
        Collection $weatherRows,
        Collection $latestSnapshots,
        Collection $automationHints,
        Collection $adminWeatherNotices
    ): array {
        $normalizedSummary = array_merge([
            'green' => 0,
            'yellow' => 0,
            'red' => 0,
            'unknown' => 0,
        ], $summary);

        $weatherTableRows = $weatherRows->map(function ($row): array {
            $severity = strtolower((string) ($row['severity'] ?? 'unknown'));
            $sourceLabel = (string) ($row['source_label'] ?? 'Belum ada data OpenWeather');
            $isStale = (bool) ($row['is_stale'] ?? false);

            return [
                'label' => (string) ($row['label'] ?? '-'),
                'province_name' => (string) ($row['province_name'] ?? '-'),
                'total_users' => (int) ($row['total_users'] ?? 0),
                'severity' => $severity,
                'severity_badge_class' => $this->weatherSeverityBadgeClass($severity),
                'temp_label' => $this->measurementLabel($row['temp'] ?? null, ' C'),
                'rain_label' => $this->measurementLabel($row['rain'] ?? null, ' mm'),
                'wind_label' => $this->measurementLabel($row['wind'] ?? null, ' m/s'),
                'source_label' => $sourceLabel,
                'source_badge_class' => $this->weatherSourceBadgeClass($sourceLabel, $isStale),
                'bmkg_code_label' => (string) ($row['bmkg_code_label'] ?? '-'),
                'source_reason' => (string) ($row['source_reason'] ?? '-'),
                'source_reason_class' => $this->weatherSourceReasonClass((string) ($row['source_reason'] ?? '-')),
                'updated_at_label' => $this->dateTimeLabel($row['fetched_at'] ?? null),
                'cache_expiry_label' => $this->dateTimeLabel($row['snapshot_valid_until'] ?? null),
            ];
        })->values();

        $noticeRows = $adminWeatherNotices->map(function ($notice): array {
            $severity = strtolower((string) ($notice->severity ?? 'unknown'));
            $validUntil = $this->parseCarbon($notice->valid_until ?? null);
            $isExpired = $validUntil ? $validUntil->isPast() : false;
            $isActive = (bool) ($notice->is_active ?? false);

            $statusLabel = 'Aktif';
            $statusBadgeClass = 'border-emerald-200 bg-emerald-50 text-emerald-700';
            if (! $isActive) {
                $statusLabel = 'Nonaktif';
                $statusBadgeClass = 'border-slate-200 bg-slate-100 text-slate-700';
            } elseif ($isExpired) {
                $statusLabel = 'Expired';
                $statusBadgeClass = 'border-amber-200 bg-amber-50 text-amber-700';
            }

            return [
                'id' => (int) ($notice->id ?? 0),
                'scope' => (string) ($notice->scope ?? 'global'),
                'province_id' => ! empty($notice->province_id) ? (int) $notice->province_id : null,
                'city_id' => ! empty($notice->city_id) ? (int) $notice->city_id : null,
                'severity' => $severity,
                'severity_badge_class' => $this->noticeSeverityBadgeClass($severity),
                'status_label' => $statusLabel,
                'status_badge_class' => $statusBadgeClass,
                'target_label' => $this->noticeTargetLabel($notice),
                'created_at_label' => $this->dateTimeLabel($notice->created_at ?? null),
                'title' => (string) ($notice->title ?? ''),
                'has_title' => trim((string) ($notice->title ?? '')) !== '',
                'message' => (string) ($notice->message ?? ''),
                'valid_until_label' => $validUntil ? $validUntil->format('d M Y H:i') : 'Tanpa batas waktu',
                'valid_until_input' => $validUntil ? $validUntil->format('Y-m-d\TH:i') : '',
                'created_by_name' => (string) ($notice->created_by_name ?? ''),
                'is_active' => $isActive,
                'toggle_label' => $isActive ? 'Nonaktifkan' : 'Aktifkan',
            ];
        })->values();

        $snapshotRows = $latestSnapshots->map(function ($snapshot): array {
            return [
                'kind' => strtoupper((string) ($snapshot->kind ?? '-')),
                'location_label' => strtoupper((string) ($snapshot->location_type ?? '-')) . ' #' . (string) ($snapshot->location_id ?? '-'),
                'fetched_at_label' => $this->dateTimeLabel($snapshot->fetched_at ?? null),
                'valid_until_label' => $this->dateTimeLabel($snapshot->valid_until ?? null),
            ];
        })->values();

        return [
            'summary' => $normalizedSummary,
            'noticePriorityCount' => (int) (($normalizedSummary['red'] ?? 0) + ($normalizedSummary['yellow'] ?? 0)),
            'weatherRowCount' => $weatherTableRows->count(),
            'automationHintsCount' => $automationHints->count(),
            'weatherTableRows' => $weatherTableRows,
            'noticeRows' => $noticeRows,
            'snapshotRows' => $snapshotRows,
        ];
    }

    private function weatherSeverityBadgeClass(string $severity): string
    {
        return match ($severity) {
            'green' => 'bg-emerald-100 text-emerald-800',
            'yellow' => 'bg-amber-100 text-amber-800',
            'red' => 'bg-rose-100 text-rose-800',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    private function noticeSeverityBadgeClass(string $severity): string
    {
        return match ($severity) {
            'red' => 'border-rose-200 bg-rose-50 text-rose-700',
            'yellow' => 'border-amber-200 bg-amber-50 text-amber-700',
            'green' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            default => 'border-slate-200 bg-slate-100 text-slate-700',
        };
    }

    private function weatherSourceBadgeClass(string $sourceLabel, bool $isStale): string
    {
        if (str_contains(strtolower($sourceLabel), 'belum ada')) {
            return 'bg-slate-100 text-slate-700';
        }

        if ($isStale) {
            return 'bg-amber-100 text-amber-800';
        }

        if (str_contains(strtolower($sourceLabel), 'bmkg')) {
            return 'bg-indigo-100 text-indigo-800';
        }

        if (str_contains(strtolower($sourceLabel), 'openweather')) {
            return 'bg-cyan-100 text-cyan-800';
        }

        return 'bg-slate-100 text-slate-700';
    }

    private function weatherSourceReasonClass(string $reason): string
    {
        $normalized = strtolower(trim($reason));

        if (str_contains($normalized, 'fallback ke bmkg') || str_contains($normalized, 'berhasil diambil dari bmkg')) {
            return 'text-indigo-700';
        }

        if (str_contains($normalized, 'tidak mengembalikan data') || str_contains($normalized, 'kosong')) {
            return 'text-amber-700';
        }

        if (str_contains($normalized, 'valid')) {
            return 'text-emerald-700';
        }

        return 'text-slate-600';
    }

    private function measurementLabel(mixed $value, string $suffix): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return $value . $suffix;
    }

    private function noticeTargetLabel(object $notice): string
    {
        if (! empty($notice->city_id)) {
            $cityLabel = trim(((string) ($notice->city_type ?? '')) . ' ' . ((string) ($notice->city_name ?? 'Kota')));

            return $cityLabel !== '' ? $cityLabel : 'Kota';
        }

        if (! empty($notice->province_id)) {
            return 'Provinsi ' . ((string) ($notice->province_name ?? '-'));
        }

        return 'Semua Lokasi';
    }

    private function dateTimeLabel(mixed $value): string
    {
        $parsed = $this->parseCarbon($value);
        if (! $parsed) {
            return '-';
        }

        return $parsed->format('d M Y H:i');
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
}
