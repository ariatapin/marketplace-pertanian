<?php

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class AdminProcurementViewModelFactory
{
    private const ALL_STATUSES = [
        'pending',
        'approved',
        'processing',
        'shipped',
        'delivered',
        'cancelled',
    ];

    public function make(
        array $summary,
        Collection $adminProducts,
        LengthAwarePaginator|Collection $adminOrders
    ): array {
        $summary = $this->normalizeSummary($summary);

        $adminProducts = $adminProducts->map(function ($product) {
            $isActive = (bool) ($product->is_active ?? false);
            $unitLabel = $this->normalizeUnit($product->unit ?? null);
            $warehouseId = ! empty($product->warehouse_id) ? (int) $product->warehouse_id : null;
            $warehouseCode = trim((string) ($product->warehouse_code ?? ''));
            $warehouseName = trim((string) ($product->warehouse_name ?? ''));
            $warehouseLabel = $warehouseName !== ''
                ? ($warehouseCode !== '' ? "{$warehouseCode} - {$warehouseName}" : $warehouseName)
                : '-';

            return (object) [
                'id' => (int) ($product->id ?? 0),
                'name' => (string) ($product->name ?? '-'),
                'description' => trim((string) ($product->description ?? '')),
                'price_value' => (float) ($product->price ?? 0),
                'unit_value' => $unitLabel,
                'min_order_qty_value' => (int) ($product->min_order_qty ?? 1),
                'stock_qty_value' => (int) ($product->stock_qty ?? 0),
                'is_active_value' => $isActive,
                'warehouse_id_value' => $warehouseId,
                'warehouse_code_value' => $warehouseCode,
                'warehouse_name_value' => $warehouseName,
                'warehouse_label' => $warehouseLabel,
                'unit_label' => $unitLabel,
                'price_label' => 'Rp' . number_format((float) ($product->price ?? 0), 0, ',', '.') . ' / ' . $unitLabel,
                'min_order_qty_label' => number_format((int) ($product->min_order_qty ?? 0)) . ' ' . $unitLabel,
                'stock_qty_label' => number_format((int) ($product->stock_qty ?? 0)) . ' ' . $unitLabel,
                'status_label' => $isActive ? 'Aktif' : 'Nonaktif',
                'status_badge_class' => $isActive
                    ? 'rounded bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800'
                    : 'rounded bg-slate-200 px-2 py-1 text-xs font-semibold text-slate-700',
            ];
        });

        $adminOrders = $this->mapItems($adminOrders, function ($order) {
            $status = (string) ($order->status ?? 'pending');
            $paymentStatusRaw = $order->payment_status ?? null;
            $paymentStatus = is_string($paymentStatusRaw) ? $paymentStatusRaw : '';
            [$paymentStatusLabel, $paymentBadgeClass] = $this->paymentStatusMeta($paymentStatus);
            $allowedTargets = collect($order->allowed_status_targets ?? [])
                ->map(fn ($value) => (string) $value)
                ->filter(fn ($value) => $value !== '')
                ->values()
                ->all();
            $nextTargets = collect($allowedTargets)
                ->reject(fn ($target) => $target === $status)
                ->values()
                ->all();
            $hasActionableTransition = count($nextTargets) > 0;

            $items = collect($order->items ?? []);
            $latestHistory = is_object($order->latest_history ?? null) ? $order->latest_history : null;

            return (object) [
                'id' => (int) ($order->id ?? 0),
                'status' => $status,
                'status_label' => strtoupper($status),
                'mitra_name_label' => (string) (($order->mitra_name ?? '') !== '' ? $order->mitra_name : '-'),
                'mitra_email_label' => (string) (($order->mitra_email ?? '') !== '' ? $order->mitra_email : '-'),
                'item_qty_total_label' => number_format((int) ($order->item_qty_total ?? 0)),
                'item_names_preview' => $this->itemNamesPreview($items),
                'has_items_preview' => $items->isNotEmpty(),
                'total_amount_label' => 'Rp' . number_format((float) ($order->total_amount ?? 0), 0, ',', '.'),
                'notes_label' => (string) (($order->notes ?? '') !== '' ? $order->notes : '-'),
                'created_at_label' => $this->dateTimeLabel($order->created_at ?? null),
                'payment_status' => $paymentStatus,
                'payment_status_label' => $paymentStatusLabel,
                'payment_badge_class' => $paymentBadgeClass,
                'payment_method_label' => $this->paymentMethodLabel($order->payment_method ?? null),
                'paid_amount_label' => $this->moneyLabel($order->paid_amount ?? null),
                'payment_submitted_at_label' => $this->dateTimeLabel($order->payment_submitted_at ?? null),
                'payment_verified_at_label' => $this->dateTimeLabel($order->payment_verified_at ?? null),
                'payment_note_label' => (string) (($order->payment_note ?? '') !== '' ? $order->payment_note : '-'),
                'payment_proof_url' => (string) (($order->payment_proof_url ?? '') !== '' ? $order->payment_proof_url : ''),
                'has_payment_proof' => trim((string) ($order->payment_proof_url ?? '')) !== '',
                'can_verify_payment' => $paymentStatus === 'pending_verification',
                'has_latest_history' => $latestHistory !== null,
                'latest_history_from_label' => strtoupper((string) ($latestHistory?->from_status ?? 'null')),
                'latest_history_to_label' => strtoupper((string) ($latestHistory?->to_status ?? '-')),
                'latest_history_actor_label' => $this->historyActorLabel($latestHistory),
                'latest_history_actor_role_label' => (string) ($latestHistory?->actor_role ?? '-'),
                'latest_history_created_at_label' => $this->dateTimeLabel($latestHistory?->created_at ?? null),
                'quick_actions' => collect($nextTargets)->map(function ($target): array {
                    return [
                        'status' => $target,
                        'label' => $this->statusActionLabel($target),
                        'class' => $this->quickActionClass($target),
                    ];
                })->values()->all(),
                'manual_status_options' => collect(self::ALL_STATUSES)->map(function ($statusOption) use ($status, $allowedTargets): array {
                    return [
                        'value' => $statusOption,
                        'label' => strtoupper($statusOption),
                        'selected' => $statusOption === $status,
                        'disabled' => ! in_array($statusOption, $allowedTargets, true),
                    ];
                })->values()->all(),
                'has_actionable_transition' => $hasActionableTransition,
            ];
        });

        return [
            'summary' => $summary,
            'adminProducts' => $adminProducts,
            'adminOrders' => $adminOrders,
        ];
    }

    private function normalizeSummary(array $summary): array
    {
        $summary = array_merge([
            'total_admin_products' => 0,
            'active_admin_products' => 0,
            'low_stock_admin_products' => 0,
            'pending_orders' => 0,
            'processing_orders' => 0,
            'shipped_orders' => 0,
            'new_orders_today' => 0,
            'pending_payment_verification_orders' => 0,
            'paid_orders' => 0,
        ], $summary);

        $summary['total_admin_products_label'] = number_format((int) $summary['total_admin_products']);
        $summary['active_admin_products_label'] = number_format((int) $summary['active_admin_products']);
        $summary['low_stock_admin_products_label'] = number_format((int) $summary['low_stock_admin_products']);
        $summary['pending_orders_label'] = number_format((int) $summary['pending_orders']);
        $summary['processing_orders_label'] = number_format((int) $summary['processing_orders']);
        $summary['shipped_orders_label'] = number_format((int) $summary['shipped_orders']);
        $summary['new_orders_today_label'] = number_format((int) $summary['new_orders_today']);
        $summary['pending_payment_verification_orders_label'] = number_format((int) $summary['pending_payment_verification_orders']);
        $summary['paid_orders_label'] = number_format((int) $summary['paid_orders']);

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

    private function itemNamesPreview(Collection $items): string
    {
        if ($items->isEmpty()) {
            return '';
        }

        $names = $items->pluck('product_name')->filter(fn ($name) => (string) $name !== '');
        $preview = $names->take(2)->implode(', ');

        if ($preview === '') {
            return '';
        }

        return $names->count() > 2 ? $preview . '...' : $preview;
    }

    private function historyActorLabel(?object $latestHistory): string
    {
        if (! $latestHistory) {
            return '-';
        }

        $actorName = trim((string) ($latestHistory->actor_name ?? ''));
        if ($actorName !== '') {
            return $actorName;
        }

        return 'User #' . (string) ($latestHistory->actor_user_id ?? '-');
    }

    private function statusActionLabel(string $status): string
    {
        return match ($status) {
            'approved' => 'Approve',
            'processing' => 'Proses',
            'shipped' => 'Kirim',
            'delivered' => 'Selesai',
            'cancelled' => 'Cancel',
            default => strtoupper($status),
        };
    }

    private function quickActionClass(string $status): string
    {
        return match ($status) {
            'approved' => 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200',
            'processing' => 'bg-indigo-100 text-indigo-800 hover:bg-indigo-200',
            'shipped' => 'bg-sky-100 text-sky-800 hover:bg-sky-200',
            'delivered' => 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200',
            'cancelled' => 'bg-rose-100 text-rose-800 hover:bg-rose-200',
            default => 'bg-slate-100 text-slate-800 hover:bg-slate-200',
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

    private function paymentMethodLabel(mixed $value): string
    {
        $method = trim((string) $value);
        if ($method === '') {
            return '-';
        }

        return match ($method) {
            'bank_transfer' => 'Bank Transfer',
            'gopay' => 'GoPay',
            'ovo' => 'OVO',
            'dana' => 'DANA',
            'linkaja' => 'LinkAja',
            'shopeepay' => 'ShopeePay',
            'other_wallet' => 'E-Wallet Lainnya',
            default => ucfirst(str_replace('_', ' ', $method)),
        };
    }

    private function moneyLabel(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return 'Rp' . number_format((float) $value, 0, ',', '.');
    }

    private function paymentStatusMeta(string $status): array
    {
        return match ($status) {
            'unpaid' => ['Belum Bayar', 'rounded bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700'],
            'pending_verification' => ['Menunggu Verifikasi', 'rounded bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800'],
            'paid' => ['Lunas', 'rounded bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800'],
            'rejected' => ['Ditolak', 'rounded bg-rose-100 px-2 py-1 text-xs font-semibold text-rose-800'],
            default => ['-', 'rounded bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700'],
        };
    }

    private function normalizeUnit(mixed $value): string
    {
        $unit = strtolower(trim((string) $value));

        return $unit !== '' ? $unit : 'kg';
    }
}
