<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Concerns\HandlesProcurementPaymentSupport;
use App\Http\Controllers\Controller;
use App\Services\Procurement\ProcurementOrderCancellationService;
use App\Support\ProcurementOrderStatusLogger;
use App\Support\ProcurementStatusTransition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AdminProcurementApiController extends Controller
{
    use ApiResponse;
    use HandlesProcurementPaymentSupport;

    public function __construct(
        private readonly ProcurementStatusTransition $statusTransition,
        private readonly ProcurementOrderStatusLogger $statusLogger,
        private readonly ProcurementOrderCancellationService $orderCancellationService
    )
    {
    }

    public function products()
    {
        $query = DB::table('admin_products as product')
            ->orderByDesc('product.id');

        $columns = ['product.*'];
        if (Schema::hasTable('warehouses')) {
            $query->leftJoin('warehouses as warehouse', 'warehouse.id', '=', 'product.warehouse_id');
            $columns[] = 'warehouse.code as warehouse_code';
            $columns[] = 'warehouse.name as warehouse_name';
        } else {
            $columns[] = DB::raw('null as warehouse_code');
            $columns[] = DB::raw('null as warehouse_name');
        }

        $rows = $query->get($columns);
        return $this->apiSuccess($rows, 'Data produk pengadaan berhasil diambil.');
    }

    public function createProduct(Request $request)
    {
        if (! Schema::hasTable('warehouses')) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Modul gudang belum tersedia. Jalankan migration terbaru.',
            ]);
        }

        $activeWarehouseCount = (int) DB::table('warehouses')
            ->where('is_active', true)
            ->count();

        if ($activeWarehouseCount <= 0) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Belum ada gudang aktif. Tambahkan gudang dulu di menu Gudang.',
            ]);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'unit' => 'required|string|max:20',
            'min_order_qty' => 'required|integer|min:1',
            'stock_qty' => 'required|integer|min:0',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'is_active' => 'required|boolean',
        ]);

        $data['unit'] = strtolower(trim((string) ($data['unit'] ?? 'kg')));
        if ($data['unit'] === '') {
            $data['unit'] = 'kg';
        }

        $warehouse = DB::table('warehouses')
            ->where('id', (int) $data['warehouse_id'])
            ->first(['id', 'is_active']);

        if (! $warehouse || ! (bool) $warehouse->is_active) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Gudang yang dipilih tidak aktif. Pilih gudang aktif.',
            ]);
        }

        $id = DB::table('admin_products')->insertGetId(array_merge($data, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        return $this->apiSuccess([
            'admin_product_id' => $id,
        ], 'Produk pengadaan berhasil dibuat.', 201);
    }

    public function orders()
    {
        $rows = DB::table('admin_orders')
            ->leftJoin('users', 'users.id', '=', 'admin_orders.mitra_id')
            ->select('admin_orders.*', 'users.name as mitra_name', 'users.email as mitra_email')
            ->orderByDesc('admin_orders.id')
            ->get();

        return $this->apiSuccess($rows, 'Data order pengadaan berhasil diambil.');
    }

    public function setOrderStatus(Request $request, int $adminOrderId)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,approved,processing,shipped,cancelled',
        ]);
        $restockResult = null;

        DB::transaction(function () use ($adminOrderId, $data, $request, &$restockResult) {
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
                    'Status diperbarui oleh admin (API)'
                );
            }
        });

        return $this->apiSuccess([
            'status' => $data['status'],
            'stock_settlement' => null,
            'restock' => $restockResult,
        ], "Status order #{$adminOrderId} berhasil diubah.");
    }

    public function setPaymentStatus(Request $request, int $adminOrderId)
    {
        if (! $this->hasPaymentColumns()) {
            throw ValidationException::withMessages([
                'payment_status' => 'Fitur pembayaran pengadaan belum aktif. Jalankan migration terbaru.',
            ]);
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

        return $this->apiSuccess(
            ['payment_status' => $data['payment_status']],
            "Pembayaran order pengadaan #{$adminOrderId} berhasil diperbarui ke {$data['payment_status']}."
        );
    }
}
