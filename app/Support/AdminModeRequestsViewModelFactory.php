<?php

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class AdminModeRequestsViewModelFactory
{
    public function make(LengthAwarePaginator|Collection $rows, LengthAwarePaginator|Collection $mitraRows): array
    {
        return [
            'rows' => $this->mapConsumerRows($rows),
            'mitraRows' => $this->mapMitraRows($mitraRows),
        ];
    }

    private function mapConsumerRows(LengthAwarePaginator|Collection $rows): LengthAwarePaginator|Collection
    {
        return $this->mapItems($rows, function ($row) {
            $status = (string) ($row->mode_status ?? 'none');
            $requestedMode = (string) ($row->requested_mode ?? '');

            return (object) [
                'user_id' => (int) ($row->user_id ?? 0),
                'name' => (string) ($row->name ?? '-'),
                'email' => (string) ($row->email ?? '-'),
                'mode' => (string) ($row->mode ?? '-'),
                'mode_status' => $status,
                'requested_mode' => $requestedMode !== '' ? $requestedMode : null,
                'status_color' => $this->consumerStatusColor($status),
                'updated_at_label' => $this->dateTimeLabel($row->updated_at ?? null),
                'can_review' => $status === 'pending' && in_array($requestedMode, ['affiliate', 'farmer_seller'], true),
            ];
        });
    }

    private function mapMitraRows(LengthAwarePaginator|Collection $rows): LengthAwarePaginator|Collection
    {
        return $this->mapItems($rows, function ($row) {
            $status = (string) ($row->status ?? 'draft');

            $docs = [
                ['label' => 'KTP', 'value' => (string) ($row->ktp_url ?? '')],
                ['label' => 'NPWP', 'value' => (string) ($row->npwp_url ?? '')],
                ['label' => 'NIB', 'value' => (string) ($row->nib_url ?? '')],
                ['label' => 'Foto Gudang', 'value' => (string) ($row->warehouse_building_photo_url ?? '')],
                ['label' => 'Sertifikasi', 'value' => (string) ($row->special_certification_url ?? '')],
            ];

            $docs = collect($docs)->map(function (array $doc): array {
                $docValue = trim($doc['value']);
                $docUrl = '';
                if ($docValue !== '') {
                    if (Str::startsWith($docValue, ['http://', 'https://', '/storage/', 'storage/'])) {
                        $docUrl = $docValue;
                    } else {
                        $docUrl = asset('storage/' . ltrim($docValue, '/'));
                    }
                }

                return [
                    'label' => $doc['label'],
                    'url' => $docUrl,
                    'exists' => $docUrl !== '',
                ];
            })->values()->all();

            return (object) [
                'id' => (int) ($row->id ?? 0),
                'user_id' => (int) ($row->user_id ?? 0),
                'status' => $status,
                'status_color' => $this->mitraStatusColor($status),
                'notes' => (string) ($row->notes ?? ''),
                'user_name_label' => (string) ($row->user_name ?? $row->name ?? $row->full_name ?? '-'),
                'user_email_label' => (string) ($row->user_email ?? $row->email ?? '-'),
                'user_role_label' => strtoupper((string) ($row->user_role ?? $row->role ?? '-')),
                'full_name' => (string) ($row->full_name ?? '-'),
                'email' => (string) ($row->email ?? '-'),
                'warehouse_address' => (string) ($row->warehouse_address ?? ''),
                'warehouse_capacity_label' => ! empty($row->warehouse_capacity)
                    ? number_format((int) $row->warehouse_capacity) . ' kg'
                    : '-',
                'products_managed' => (string) ($row->products_managed ?? ''),
                'docs' => $docs,
                'updated_at_label' => $this->dateTimeLabel($row->updated_at ?? null),
                'submitted_at_label' => $this->dateTimeLabel($row->submitted_at ?? null),
                'decided_at_label' => $this->dateTimeLabel($row->decided_at ?? null),
                'has_submitted_at' => ! empty($row->submitted_at),
                'has_decided_at' => ! empty($row->decided_at),
                'decided_by_name' => (string) ($row->decided_by_name ?? ''),
                'can_review' => $status === 'pending',
            ];
        });
    }

    private function mapItems(LengthAwarePaginator|Collection $rows, callable $mapper): LengthAwarePaginator|Collection
    {
        if ($rows instanceof LengthAwarePaginator) {
            $mapped = $rows->getCollection()->map($mapper);
            $rows->setCollection($mapped);

            return $rows;
        }

        return $rows->map($mapper);
    }

    private function consumerStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'bg-amber-100 text-amber-800',
            'approved' => 'bg-green-100 text-green-800',
            'rejected' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-700',
        };
    }

    private function mitraStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'bg-amber-100 text-amber-800',
            'approved' => 'bg-green-100 text-green-800',
            'rejected' => 'bg-red-100 text-red-800',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    private function dateTimeLabel(mixed $value): string
    {
        if (empty($value)) {
            return '-';
        }

        try {
            return Carbon::parse($value)->format('d M Y H:i');
        } catch (\Throwable) {
            return '-';
        }
    }
}
