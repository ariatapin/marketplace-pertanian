<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class AdminSettingsViewModelFactory
{
    public function make(array $mitraFlag, Collection $announcements, array $automationFlag = []): array
    {
        $isEnabled = (bool) ($mitraFlag['is_enabled'] ?? false);
        $mitraFlagView = [
            'is_enabled' => $isEnabled,
            'description' => (string) ($mitraFlag['description'] ?? ''),
            'status_label' => $isEnabled ? 'OPEN' : 'CLOSED',
            'status_class' => $isEnabled
                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                : 'border-rose-200 bg-rose-50 text-rose-700',
        ];
        $automationEnabled = (bool) ($automationFlag['is_enabled'] ?? false);
        $automationFlagView = [
            'is_enabled' => $automationEnabled,
            'description' => (string) ($automationFlag['description'] ?? ''),
            'status_label' => $automationEnabled ? 'AKTIF' : 'NONAKTIF',
            'status_class' => $automationEnabled
                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                : 'border-rose-200 bg-rose-50 text-rose-700',
        ];

        $announcementRows = $announcements->map(function ($item): array {
            $type = (string) ($item->type ?? 'info');
            $isActive = (bool) ($item->is_active ?? false);

            return [
                'id' => (int) ($item->id ?? 0),
                'type' => $type,
                'title' => (string) ($item->title ?? ''),
                'message' => (string) ($item->message ?? ''),
                'image_url' => (string) ($item->image_url ?? ''),
                'image_src' => $this->resolveImageSource((string) ($item->image_url ?? '')),
                'cta_label' => (string) ($item->cta_label ?? ''),
                'cta_url' => (string) ($item->cta_url ?? ''),
                'sort_order' => (int) ($item->sort_order ?? 0),
                'is_active' => $isActive,
                'status_label' => $isActive ? 'Active' : 'Nonaktif',
                'status_badge_class' => $isActive
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                    : 'border-slate-200 bg-slate-100 text-slate-600',
                'type_badge_class' => $this->typeBadgeClass($type),
                'starts_at_input' => $this->toDateTimeLocal($item->starts_at ?? null),
                'ends_at_input' => $this->toDateTimeLocal($item->ends_at ?? null),
            ];
        })->values();

        return [
            'mitraFlag' => $mitraFlagView,
            'automationFlag' => $automationFlagView,
            'announcementRows' => $announcementRows,
        ];
    }

    private function typeBadgeClass(string $type): string
    {
        return match ($type) {
            'banner' => 'border-cyan-200 bg-cyan-50 text-cyan-700',
            'promo' => 'border-amber-200 bg-amber-50 text-amber-700',
            default => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        };
    }

    private function toDateTimeLocal(mixed $value): string
    {
        if (! $value) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            return '';
        }
    }

    private function resolveImageSource(string $image): string
    {
        $image = trim($image);
        if ($image === '') {
            return '';
        }

        if (Str::startsWith($image, ['http://', 'https://'])) {
            return $image;
        }

        if (Str::startsWith($image, '/storage/')) {
            return $image;
        }

        if (Str::startsWith($image, 'storage/')) {
            return '/' . ltrim($image, '/');
        }

        return asset('storage/' . ltrim($image, '/'));
    }
}
