<?php

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class AdminUsersViewModelFactory
{
    public function make(array $summary, LengthAwarePaginator|Collection $rows): array
    {
        $normalizedSummary = array_merge([
            'total_users' => 0,
            'total_admin' => 0,
            'total_mitra' => 0,
            'total_consumer' => 0,
            'pending_mode' => 0,
            'suspended_users' => 0,
            'blocked_users' => 0,
        ], $summary);

        $rows = $this->mapItems($rows, function ($row) {
            $modeStatus = (string) ($row->mode_status ?? 'none');
            $isSuspended = (bool) ($row->is_suspended ?? false);
            $rawSuspensionNote = trim((string) ($row->suspension_note ?? ''));
            $isBlocked = $isSuspended && preg_match('/^\[BLOCKED\]/i', $rawSuspensionNote) === 1;
            $cleanSuspensionNote = preg_replace('/^\[(BLOCKED|SUSPEND)\]\s*/i', '', $rawSuspensionNote);
            $cleanSuspensionNote = trim((string) $cleanSuspensionNote);
            $displaySuspensionNote = $cleanSuspensionNote !== '' ? $cleanSuspensionNote : '-';

            return (object) [
                'id' => (int) ($row->id ?? 0),
                'name' => (string) ($row->name ?? '-'),
                'email' => (string) ($row->email ?? '-'),
                'role' => (string) ($row->role ?? '-'),
                'phone_label' => (string) (($row->phone_number ?? '') !== '' ? $row->phone_number : '-'),
                'mode_label' => (string) (($row->mode ?? '') !== '' ? $row->mode : '-'),
                'mode_status_label' => $this->modeStatusLabel($modeStatus),
                'mode_status_color' => $this->modeStatusColor($modeStatus),
                'requested_mode_label' => (string) (($row->requested_mode ?? '') !== '' ? $row->requested_mode : '-'),
                'created_at_label' => $this->dateLabel($row->created_at ?? null),
                'is_suspended' => $isSuspended,
                'is_blocked' => $isBlocked,
                'suspended_at_label' => $this->dateLabel($row->suspended_at ?? null),
                'suspension_note_label' => $displaySuspensionNote,
                'suspension_badge_label' => $isSuspended
                    ? ($isBlocked ? 'BLOKIR' : 'SUSPEND')
                    : 'AKTIF',
                'suspension_badge_class' => $isSuspended
                    ? ($isBlocked ? 'bg-rose-200 text-rose-800' : 'bg-amber-100 text-amber-800')
                    : 'bg-emerald-100 text-emerald-700',
            ];
        });

        return [
            'summary' => $normalizedSummary,
            'rows' => $rows,
        ];
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

    private function modeStatusLabel(string $modeStatus): string
    {
        return match ($modeStatus) {
            'approved' => 'aktif',
            'pending' => 'pending',
            'rejected' => 'tolak',
            default => 'none',
        };
    }

    private function modeStatusColor(string $modeStatus): string
    {
        return match ($modeStatus) {
            'pending' => 'bg-amber-100 text-amber-800',
            'approved' => 'bg-emerald-100 text-emerald-800',
            'rejected' => 'bg-red-100 text-red-700',
            'none' => 'bg-slate-100 text-slate-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }

    private function dateLabel(mixed $value): string
    {
        if (empty($value)) {
            return '-';
        }

        try {
            return Carbon::parse($value)->format('d M Y');
        } catch (\Throwable) {
            return '-';
        }
    }
}
