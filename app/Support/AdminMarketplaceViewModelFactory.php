<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

final class AdminMarketplaceViewModelFactory
{
    public function make(
        array $summary,
        Collection $marketplaceRows,
        Collection $notificationRows,
        Collection $announcementRows
    ): array
    {
        // CATATAN-AUDIT: Factory ini menormalkan payload modul Marketplace admin agar view tidak query/format sendiri.
        $normalizedSummary = array_merge([
            'mitra_products' => 0,
            'mitra_products_out_of_stock' => 0,
            'farmer_harvest_pending' => 0,
            'farmer_harvest_approved' => 0,
            'farmer_harvest_rejected' => 0,
            'notification_total' => 0,
            'notification_manual' => 0,
            'notification_auto' => 0,
            'notification_active' => 0,
            'announcements_total' => 0,
            'announcements_active' => 0,
            'promos_active' => 0,
            'banners_active' => 0,
            'mitra_submission_open' => false,
            'mitra_pending' => 0,
            'mitra_approved' => 0,
            'mitra_rejected' => 0,
        ], $summary);

        $marketRowsView = $marketplaceRows->map(function ($row): array {
            $source = (string) ($row->source ?? '');
            $status = (string) ($row->status ?? 'unknown');

            return [
                'id' => (int) ($row->id ?? 0),
                'source' => $source,
                'name' => (string) ($row->name ?? '-'),
                'owner_name' => (string) (($row->owner_name ?? '') !== '' ? $row->owner_name : '-'),
                'owner_email' => (string) (($row->owner_email ?? '') !== '' ? $row->owner_email : '-'),
                'price_label' => 'Rp' . number_format((float) ($row->price ?? 0), 0, ',', '.'),
                'stock_label' => number_format((int) ($row->stock_qty ?? 0)),
                'status' => $status,
                'is_farmer' => $source === 'farmer',
                'status_options' => ['pending', 'approved', 'rejected'],
            ];
        })->values();

        $notificationRowsView = $notificationRows->map(function ($row): array {
            $isAuto = empty($row->created_by);
            $validUntil = $row->valid_until ? Carbon::parse((string) $row->valid_until) : null;
            $status = ((bool) ($row->is_active ?? false) && (! $validUntil || $validUntil->greaterThanOrEqualTo(now())))
                ? 'Aktif'
                : 'Nonaktif';

            return [
                'id' => (int) ($row->id ?? 0),
                'type_label' => $isAuto ? 'Otomatis' : 'Manual',
                'scope_label' => strtoupper((string) ($row->scope ?? 'global')),
                'severity_label' => strtoupper((string) ($row->severity ?? 'unknown')),
                'title' => trim((string) ($row->title ?? '')) !== '' ? (string) $row->title : 'Notifikasi Cuaca',
                'message' => trim((string) ($row->message ?? '')) !== '' ? (string) $row->message : '-',
                'creator_label' => $isAuto ? 'Sistem' : (trim((string) ($row->creator_name ?? '')) !== '' ? (string) $row->creator_name : 'Admin'),
                'valid_until_label' => $validUntil ? $validUntil->format('d M Y H:i') : '-',
                'status_label' => $status,
                'created_at_label' => ! empty($row->created_at)
                    ? Carbon::parse((string) $row->created_at)->format('d M Y H:i')
                    : '-',
            ];
        })->values();

        $announcementRowsView = $announcementRows->map(function ($row): array {
            $startsAt = $row->starts_at ? Carbon::parse((string) $row->starts_at) : null;
            $endsAt = $row->ends_at ? Carbon::parse((string) $row->ends_at) : null;
            $isLive = (bool) ($row->is_active ?? false)
                && (! $startsAt || $startsAt->lessThanOrEqualTo(now()))
                && (! $endsAt || $endsAt->greaterThanOrEqualTo(now()));

            $type = (string) ($row->type ?? 'info');
            $typeLabel = match ($type) {
                'promo' => 'Promo',
                'banner' => 'Banner',
                default => 'Info',
            };

            return [
                'id' => (int) ($row->id ?? 0),
                'type_label' => $typeLabel,
                'title' => trim((string) ($row->title ?? '')) !== '' ? (string) $row->title : '-',
                'status_label' => $isLive ? 'Tayang' : 'Tidak Tayang',
                'window_label' => $this->formatWindow($startsAt, $endsAt),
                'updated_by_label' => trim((string) ($row->updated_by_name ?? '')) !== '' ? (string) $row->updated_by_name : 'Admin',
                'updated_at_label' => ! empty($row->updated_at)
                    ? Carbon::parse((string) $row->updated_at)->format('d M Y H:i')
                    : '-',
            ];
        })->values();

        return [
            'summary' => $normalizedSummary,
            'marketRowsView' => $marketRowsView,
            'marketRowsCount' => $marketRowsView->count(),
            'notificationRowsView' => $notificationRowsView,
            'notificationRowsCount' => $notificationRowsView->count(),
            'announcementRowsView' => $announcementRowsView,
            'announcementRowsCount' => $announcementRowsView->count(),
        ];
    }

    private function formatWindow(?Carbon $startsAt, ?Carbon $endsAt): string
    {
        $startLabel = $startsAt ? $startsAt->format('d M Y H:i') : '-';
        $endLabel = $endsAt ? $endsAt->format('d M Y H:i') : '-';

        return $startLabel . ' s/d ' . $endLabel;
    }
}
