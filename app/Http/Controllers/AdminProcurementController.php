<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Concerns\DetectsJsonRequest;
use App\Http\Controllers\Concerns\HandlesProcurementPaymentSupport;
use App\Services\Procurement\ProcurementOrderCancellationService;
use App\Support\ProcurementOrderStatusLogger;
use App\Support\ProcurementStatusTransition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AdminProcurementController extends Controller
{
    use ApiResponse;
    use DetectsJsonRequest;
    use HandlesProcurementPaymentSupport;

    public function __construct(
        private readonly ProcurementStatusTransition $statusTransition,
        private readonly ProcurementOrderStatusLogger $statusLogger,
        private readonly ProcurementOrderCancellationService $orderCancellationService
    )
    {
    }

    private function responseForUi(Request $request, string $message, array $payload = [])
    {
        if ($this->shouldReturnJson($request)) {
            return $this->apiSuccess($payload, $message);
        }

        return back()->with('status', $message);
    }

    private function allowedStatusTargetsForAdmin(string $currentStatus): array
    {
        return collect($this->statusTransition->allowedTargets($currentStatus))
            ->reject(fn ($target) => (string) $target === 'delivered')
            ->values()
            ->all();
    }

    public function setOrderStatus(Request $request, int $adminOrderId)
    {
        $this->authorize('access-admin');

        $data = $request->validate([
            'status' => 'required|in:pending,approved,processing,shipped,cancelled',
            'note' => 'nullable|string|max:255',
        ]);

        $note = trim((string) ($data['note'] ?? ''));
        $note = $note === '' ? 'Status diperbarui oleh admin' : $note;
        $restockResult = null;

        DB::transaction(function () use ($adminOrderId, $data, $request, $note, &$restockResult) {
            $order = DB::table('admin_orders')
                ->where('id', $adminOrderId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw ValidationException::withMessages(['order' => 'Admin order tidak ditemukan']);
            }

            $currentStatus = (string) $order->status;
            $targetStatus = $data['status'];
            if (! $this->statusTransition->canTransition($currentStatus, $targetStatus)) {
                throw ValidationException::withMessages([
                    'status' => 'Transisi status tidak valid dari '
                        . $currentStatus
                        . ' ke '
                        . $targetStatus
                        . '. Status yang diizinkan: '
                        . implode(', ', $this->statusTransition->allowedTargets($currentStatus)),
                ]);
            }

            if (
                $this->hasPaymentColumns()
                && $targetStatus === 'shipped'
                && (string) ($order->payment_status ?? 'unpaid') !== 'paid'
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Order hanya bisa dikirim/selesai setelah pembayaran terverifikasi paid.',
                ]);
            }

            if ($targetStatus === 'cancelled' && $currentStatus !== 'cancelled') {
                $this->orderCancellationService->assertCancellablePaymentState($order, $this->hasPaymentColumns());
                $restockResult = $this->orderCancellationService->restoreReservedStock($adminOrderId);
            }

            DB::table('admin_orders')
                ->where('id', $adminOrderId)
                ->update([
                    'status' => $targetStatus,
                    'updated_at' => now(),
                ]);

            if ($currentStatus !== $targetStatus) {
                $this->statusLogger->log(
                    $adminOrderId,
                    $currentStatus,
                    $targetStatus,
                    (int) $request->user()->id,
                    (string) $request->user()->role,
                    $note
                );
            }
        });

        return $this->responseForUi(
            $request,
            "Status order pengadaan #{$adminOrderId} diubah ke {$data['status']}.",
            [
                'status' => $data['status'],
                'restock' => $restockResult,
            ]
        );
    }

    public function setPaymentStatus(Request $request, int $adminOrderId)
    {
        $this->authorize('access-admin');

        if (! $this->hasPaymentColumns()) {
            return back()->withErrors(['payment' => 'Fitur pembayaran pengadaan belum aktif. Jalankan migration terbaru.']);
        }

        $data = $request->validate([
            'payment_status' => 'required|in:paid,rejected',
            'payment_note' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($adminOrderId, $request, $data) {
            $order = DB::table('admin_orders')
                ->where('id', $adminOrderId)
                ->lockForUpdate()
                ->first([
                    'id',
                    'status',
                    'payment_status',
                    'total_amount',
                    'paid_amount',
                ]);

            if (! $order) {
                throw ValidationException::withMessages(['order' => 'Admin order tidak ditemukan']);
            }

            if ((string) $order->payment_status !== 'pending_verification') {
                throw ValidationException::withMessages([
                    'payment_status' => 'Pembayaran harus berstatus pending_verification sebelum diverifikasi.',
                ]);
            }

            if (strtolower(trim((string) ($order->status ?? ''))) === 'cancelled') {
                throw ValidationException::withMessages([
                    'payment_status' => 'Pembayaran order berstatus cancelled tidak dapat diverifikasi.',
                ]);
            }

            DB::table('admin_orders')
                ->where('id', $adminOrderId)
                ->update([
                    'payment_status' => (string) $data['payment_status'],
                    'payment_verified_at' => now(),
                    'payment_verified_by' => (int) $request->user()->id,
                    'payment_note' => trim((string) ($data['payment_note'] ?? '')) ?: null,
                    'updated_at' => now(),
                ]);

            if (
                (string) $data['payment_status'] === 'paid'
                && in_array((string) $order->status, ['pending', 'approved'], true)
            ) {
                DB::table('admin_orders')
                    ->where('id', $adminOrderId)
                    ->update([
                        'status' => 'processing',
                        'updated_at' => now(),
                    ]);

                $this->statusLogger->log(
                    (int) $adminOrderId,
                    (string) $order->status,
                    'processing',
                    (int) $request->user()->id,
                    (string) $request->user()->role,
                    'Status otomatis ke processing setelah pembayaran diverifikasi.'
                );
            }

            if (
                (string) $data['payment_status'] === 'paid'
                && $this->canRecordProcurementIncomeWallet()
            ) {
                DB::table('users')
                    ->where('id', (int) $request->user()->id)
                    ->lockForUpdate()
                    ->first(['id']);

                $creditedAmount = (float) (($order->paid_amount ?? null) !== null
                    ? $order->paid_amount
                    : ($order->total_amount ?? 0));

                if ($creditedAmount > 0) {
                    $this->ensureWalletLedgerMutation([
                        'wallet_id' => (int) $request->user()->id,
                        'amount' => round($creditedAmount, 2),
                        'transaction_type' => 'procurement_income',
                        'idempotency_key' => "procurement:order:{$adminOrderId}:wallet:{$request->user()->id}:income",
                        'reference_order_id' => null,
                        'reference_withdraw_id' => null,
                        'description' => "Pembayaran order pengadaan mitra #{$adminOrderId}",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ], 'Ledger wallet tidak konsisten saat mencatat pembayaran pengadaan.');
                }
            }
        });

        return $this->responseForUi(
            $request,
            "Pembayaran order pengadaan #{$adminOrderId} berhasil diperbarui ke {$data['payment_status']}.",
            ['payment_status' => $data['payment_status']]
        );
    }

    public function snapshot(Request $request)
    {
        $this->authorize('access-admin');

        $data = [
            'pending_orders' => 0,
            'processing_orders' => 0,
            'shipped_orders' => 0,
            'new_orders_today' => 0,
            'latest_order_id' => 0,
        ];

        if (Schema::hasTable('admin_orders')) {
            $data['pending_orders'] = DB::table('admin_orders')->where('status', 'pending')->count();
            $data['processing_orders'] = DB::table('admin_orders')->whereIn('status', ['approved', 'processing'])->count();
            $data['shipped_orders'] = DB::table('admin_orders')->where('status', 'shipped')->count();
            $data['new_orders_today'] = DB::table('admin_orders')->whereDate('created_at', today())->count();
            $data['latest_order_id'] = (int) (DB::table('admin_orders')->max('id') ?? 0);
        }

        return $this->apiSuccess($data, 'Snapshot procurement admin berhasil diambil.');
    }

    public function show(Request $request, int $adminOrderId)
    {
        $this->authorize('access-admin');
        $hasPaymentColumns = $this->hasPaymentColumns();

        $allowedHistoryStatuses = ['pending', 'approved', 'processing', 'shipped', 'delivered', 'cancelled'];
        $historyStatus = (string) $request->query('history_status', '');
        if (! in_array($historyStatus, $allowedHistoryStatuses, true)) {
            $historyStatus = '';
        }

        $historyActor = trim((string) $request->query('history_actor', ''));
        if (strlen($historyActor) > 100) {
            $historyActor = substr($historyActor, 0, 100);
        }

        $historyDateFrom = (string) $request->query('history_date_from', '');
        $historyDateTo = (string) $request->query('history_date_to', '');
        $datePattern = '/^\d{4}-\d{2}-\d{2}$/';
        if ($historyDateFrom !== '' && ! preg_match($datePattern, $historyDateFrom)) {
            $historyDateFrom = '';
        }
        if ($historyDateTo !== '' && ! preg_match($datePattern, $historyDateTo)) {
            $historyDateTo = '';
        }
        if ($historyDateFrom !== '' && $historyDateTo !== '' && $historyDateFrom > $historyDateTo) {
            [$historyDateFrom, $historyDateTo] = [$historyDateTo, $historyDateFrom];
        }

        $historyFilters = [
            'status' => $historyStatus,
            'actor' => $historyActor,
            'date_from' => $historyDateFrom,
            'date_to' => $historyDateTo,
        ];

        $orderQuery = DB::table('admin_orders')
            ->leftJoin('users as mitra', 'mitra.id', '=', 'admin_orders.mitra_id')
            ->where('admin_orders.id', $adminOrderId);

        if ($hasPaymentColumns) {
            $orderQuery->leftJoin('users as payment_verifier', 'payment_verifier.id', '=', 'admin_orders.payment_verified_by');
        }

        $selectColumns = [
                'admin_orders.id',
                'admin_orders.mitra_id',
                'admin_orders.total_amount',
                'admin_orders.status',
                'admin_orders.notes',
                'admin_orders.created_at',
                'admin_orders.updated_at',
                'mitra.name as mitra_name',
                'mitra.email as mitra_email'
        ];
        if ($hasPaymentColumns) {
            $selectColumns = array_merge($selectColumns, [
                'admin_orders.payment_status',
                'admin_orders.payment_method',
                'admin_orders.paid_amount',
                'admin_orders.payment_proof_url',
                'admin_orders.payment_submitted_at',
                'admin_orders.payment_verified_at',
                'admin_orders.payment_note',
                'admin_orders.payment_verified_by',
                'payment_verifier.name as payment_verified_by_name',
            ]);
        }

        $order = $orderQuery->select($selectColumns)->first();

        if (! $order) {
            abort(404, 'Order pengadaan tidak ditemukan.');
        }

        $items = collect();
        if (Schema::hasTable('admin_order_items')) {
            $items = DB::table('admin_order_items')
                ->where('admin_order_id', $adminOrderId)
                ->orderBy('id')
                ->get(['id', 'product_name', 'price_per_unit', 'unit', 'qty', 'created_at']);
        }

        $stockSettlement = null;
        if (Schema::hasTable('procurement_stock_settlements')) {
            $stockSettlement = DB::table('procurement_stock_settlements as settlement')
                ->leftJoin('users as actor', 'actor.id', '=', 'settlement.settled_by_user_id')
                ->where('settlement.admin_order_id', $adminOrderId)
                ->first([
                    'settlement.admin_order_id',
                    'settlement.mitra_id',
                    'settlement.line_count',
                    'settlement.total_qty',
                    'settlement.settled_at',
                    'settlement.settled_by_role',
                    'actor.name as settled_by_name',
                ]);
        }

        $statusHistory = collect();
        if (Schema::hasTable('admin_order_status_histories')) {
            $historyQuery = DB::table('admin_order_status_histories as h')
                ->leftJoin('users as actor', 'actor.id', '=', 'h.actor_user_id')
                ->where('h.admin_order_id', $adminOrderId);

            if ($historyFilters['status'] !== '') {
                $historyQuery->where('h.to_status', $historyFilters['status']);
            }

            if ($historyFilters['actor'] !== '') {
                $keyword = $historyFilters['actor'];
                $historyQuery->where(function ($query) use ($keyword) {
                    $query->where('actor.name', 'like', "%{$keyword}%")
                        ->orWhere('h.actor_role', 'like', "%{$keyword}%");
                });
            }

            if ($historyFilters['date_from'] !== '') {
                $historyQuery->whereDate('h.created_at', '>=', $historyFilters['date_from']);
            }

            if ($historyFilters['date_to'] !== '') {
                $historyQuery->whereDate('h.created_at', '<=', $historyFilters['date_to']);
            }

            $statusHistory = $historyQuery
                ->orderBy('h.id')
                ->get([
                    'h.from_status',
                    'h.to_status',
                    'h.note',
                    'h.created_at',
                    'h.actor_role',
                    'h.actor_user_id',
                    'actor.name as actor_name',
                ]);
        }

        if ($this->shouldReturnJson($request)) {
            return $this->apiSuccess([
                'order' => $order,
                'items' => $items,
                'stock_settlement' => $stockSettlement,
                'status_history' => $statusHistory,
                'history_filters' => $historyFilters,
                'allowed_status_targets' => $this->statusTransition->allowedTargets((string) $order->status),
            ], "Detail order pengadaan #{$adminOrderId} berhasil diambil.");
        }

        return view('admin.procurement-show', [
            'order' => $order,
            'items' => $items,
            'stockSettlement' => $stockSettlement,
            'statusHistory' => $statusHistory,
            'historyFilters' => $historyFilters,
            'allowedStatusTargets' => $this->allowedStatusTargetsForAdmin((string) $order->status),
            'hasPaymentColumns' => $hasPaymentColumns,
        ]);
    }
}
