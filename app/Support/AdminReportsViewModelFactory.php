<?php

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class AdminReportsViewModelFactory
{
    public function make(
        array $summary,
        LengthAwarePaginator|Collection $reportRows,
        array $filters,
        Collection $categoryOptions
    ): array {
        $summary = $this->normalizeSummary($summary);
        $categoryOptions = $categoryOptions
            ->filter(fn ($value) => (string) $value !== '')
            ->map(fn ($value) => (string) $value)
            ->values();

        $reportRows = $this->mapItems($reportRows, function ($row) {
            return (object) [
                'id' => (int) ($row->id ?? 0),
                'reporter_name_label' => (string) (($row->reporter_name ?? '') !== '' ? $row->reporter_name : '-'),
                'reporter_email_label' => (string) (($row->reporter_email ?? '') !== '' ? $row->reporter_email : '-'),
                'category_label' => strtoupper((string) (($row->category ?? '') !== '' ? $row->category : 'other')),
                'status_label' => strtoupper((string) ($row->status ?? '-')),
                'order_id_label' => '#' . (string) (($row->order_id ?? '') !== '' ? $row->order_id : '-'),
            ];
        });

        return [
            'summary' => $summary,
            'reportRows' => $reportRows,
            'filters' => [
                'status' => (string) ($filters['status'] ?? ''),
                'category' => (string) ($filters['category'] ?? ''),
                'q' => (string) ($filters['q'] ?? ''),
            ],
            'categoryOptions' => $categoryOptions,
        ];
    }

    public function makeDetail(object $report): object
    {
        $reporterName = (string) (($report->reporter_name ?? '') !== '' ? $report->reporter_name : 'User');
        $reporterPhoto = trim((string) ($report->reporter_photo ?? ''));
        $evidenceUrls = $this->decodeEvidenceUrls($report->evidence_urls ?? null);

        return (object) [
            'id' => (int) ($report->id ?? 0),
            'category_label' => strtoupper((string) (($report->category ?? '') !== '' ? $report->category : 'other')),
            'status_label' => strtoupper((string) ($report->status ?? '-')),
            'reporter_name_label' => $reporterName,
            'reporter_email_label' => (string) (($report->reporter_email ?? '') !== '' ? $report->reporter_email : '-'),
            'reporter_id_label' => (string) (($report->reporter_id ?? '') !== '' ? $report->reporter_id : '-'),
            'reporter_photo_url' => $reporterPhoto !== ''
                ? $reporterPhoto
                : 'https://ui-avatars.com/api/?name=' . urlencode($reporterName) . '&background=0f172a&color=ffffff',
            'order_id_label' => '#' . (string) (($report->order_id ?? '') !== '' ? $report->order_id : '-'),
            'created_at_label' => $this->dateTimeLabel($report->created_at ?? null),
            'handler_name_label' => (string) (($report->handler_name ?? '') !== '' ? $report->handler_name : '-'),
            'description_label' => (string) (($report->description ?? '') !== '' ? $report->description : 'Tidak ada deskripsi.'),
            'resolution_label' => strtoupper((string) (($report->resolution ?? '') !== '' ? $report->resolution : '-')),
            'resolution_notes_label' => (string) (($report->resolution_notes ?? '') !== '' ? $report->resolution_notes : '-'),
            'handled_at_label' => $this->dateTimeLabel($report->handled_at ?? null),
            'evidence_urls' => $evidenceUrls,
        ];
    }

    public function makeProductRows(LengthAwarePaginator|Collection $reportRows): LengthAwarePaginator|Collection
    {
        return $this->mapItems($reportRows, function ($row) {
            $productType = strtolower(trim((string) ($row->product_type ?? 'store')));
            $productTypeLabel = $productType === 'farmer' ? 'PRODUK PETANI' : 'PRODUK MITRA';

            return (object) [
                'id' => (int) ($row->id ?? 0),
                'reporter_name_label' => (string) (($row->reporter_name ?? '') !== '' ? $row->reporter_name : '-'),
                'reporter_email_label' => (string) (($row->reporter_email ?? '') !== '' ? $row->reporter_email : '-'),
                'reported_user_name_label' => (string) (($row->reported_user_name ?? '') !== '' ? $row->reported_user_name : '-'),
                'category_label' => strtoupper((string) (($row->category ?? '') !== '' ? $row->category : 'other')),
                'status_label' => strtoupper((string) ($row->status ?? '-')),
                'product_id_label' => '#' . (string) (($row->product_id ?? '') !== '' ? $row->product_id : '-'),
                'product_name_label' => (string) (($row->product_name ?? '') !== '' ? $row->product_name : '-'),
                'product_type_label' => $productTypeLabel,
            ];
        });
    }

    public function makeProductDetail(object $report): object
    {
        $reporterName = (string) (($report->reporter_name ?? '') !== '' ? $report->reporter_name : 'User');
        $reporterPhoto = trim((string) ($report->reporter_photo ?? ''));
        $productType = strtolower(trim((string) ($report->product_type ?? 'store')));

        return (object) [
            'id' => (int) ($report->id ?? 0),
            'product_type_label' => $productType === 'farmer' ? 'PRODUK PETANI' : 'PRODUK MITRA',
            'product_id_label' => '#' . (string) (($report->product_id ?? '') !== '' ? $report->product_id : '-'),
            'product_name_label' => (string) (($report->product_name ?? '') !== '' ? $report->product_name : '-'),
            'category_label' => strtoupper((string) (($report->category ?? '') !== '' ? $report->category : 'other')),
            'status_label' => strtoupper((string) ($report->status ?? '-')),
            'reporter_name_label' => $reporterName,
            'reporter_email_label' => (string) (($report->reporter_email ?? '') !== '' ? $report->reporter_email : '-'),
            'reporter_id_label' => (string) (($report->reporter_id ?? '') !== '' ? $report->reporter_id : '-'),
            'reporter_photo_url' => $reporterPhoto !== ''
                ? $reporterPhoto
                : 'https://ui-avatars.com/api/?name=' . urlencode($reporterName) . '&background=0f172a&color=ffffff',
            'reported_user_name_label' => (string) (($report->reported_user_name ?? '') !== '' ? $report->reported_user_name : '-'),
            'reported_user_email_label' => (string) (($report->reported_user_email ?? '') !== '' ? $report->reported_user_email : '-'),
            'created_at_label' => $this->dateTimeLabel($report->created_at ?? null),
            'handler_name_label' => (string) (($report->handler_name ?? '') !== '' ? $report->handler_name : '-'),
            'description_label' => (string) (($report->description ?? '') !== '' ? $report->description : 'Tidak ada deskripsi.'),
            'resolution_notes_label' => (string) (($report->resolution_notes ?? '') !== '' ? $report->resolution_notes : '-'),
            'handled_at_label' => $this->dateTimeLabel($report->handled_at ?? null),
        ];
    }

    private function normalizeSummary(array $summary): array
    {
        $summary = array_merge([
            'total_reports' => 0,
            'pending_reports' => 0,
            'under_review_reports' => 0,
            'resolved_reports' => 0,
        ], $summary);

        $summary['total_reports_label'] = number_format((int) $summary['total_reports']);
        $summary['pending_reports_label'] = number_format((int) $summary['pending_reports']);
        $summary['under_review_reports_label'] = number_format((int) $summary['under_review_reports']);
        $summary['resolved_reports_label'] = number_format((int) $summary['resolved_reports']);

        return $summary;
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

    /**
     * @return array<int, string>
     */
    private function decodeEvidenceUrls(mixed $value): array
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item) => trim((string) $item))
                ->filter(fn ($item) => $item !== '')
                ->values()
                ->all();
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($decoded)) {
                return [];
            }

            return collect($decoded)
                ->map(fn ($item) => trim((string) $item))
                ->filter(fn ($item) => $item !== '')
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
