<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Concerns\DetectsJsonRequest;
use App\Http\Controllers\Concerns\HandlesProcurementPaymentSupport;
use App\Models\User;
use App\Services\Procurement\ProcurementOrderCancellationService;
use App\Services\Procurement\ProcurementStockSettlementService;
use App\Support\ProcurementOrderStatusLogger;
use App\Support\ProcurementStatusTransition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MitraProcurementController extends Controller
{
    use ApiResponse;
    use DetectsJsonRequest;
    use HandlesProcurementPaymentSupport;

    public function __construct(
        private readonly ProcurementOrderStatusLogger $statusLogger,
        private readonly ProcurementStatusTransition $statusTransition,
        private readonly ProcurementOrderCancellationService $orderCancellationService,
        private readonly ProcurementStockSettlementService $stockSettlementService
    ) {
    }

    private function responseForUi(Request $request, string $message, array $payload = [], int $jsonStatus = 200)
    {
        if ($this->shouldReturnJson($request)) {
            return $this->apiSuccess($payload, $message, $jsonStatus);
        }

        return redirect()
            ->route('mitra.procurement.index')
            ->with('success', $message);
    }

    /**
     * @return array{balance:float,reserved_withdraw_amount:float,available_balance:float}
     */
    private function walletSnapshot(int $userId): array
    {
        if ($userId <= 0 || ! Schema::hasTable('wallet_transactions')) {
            return [
                'balance' => 0.0,
                'reserved_withdraw_amount' => 0.0,
                'available_balance' => 0.0,
            ];
        }

        $balance = (float) DB::table('wallet_transactions')
            ->where('wallet_id', $userId)
            ->sum('amount');

        $reservedWithdrawAmount = 0.0;
        if (Schema::hasTable('withdraw_requests')) {
            $reservedWithdrawAmount = (float) DB::table('withdraw_requests')
                ->where('user_id', $userId)
                ->whereIn('status', ['pending', 'approved'])
                ->sum('amount');
        }

        return [
            'balance' => $balance,
            'reserved_withdraw_amount' => $reservedWithdrawAmount,
            'available_balance' => max(0.0, round($balance - $reservedWithdrawAmount, 2)),
        ];
    }

    private function resolveAdminReceiverUserId(): int
    {
        return (int) (User::query()
            ->whereNormalizedRole('admin')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    public function createOrder(Request $request)
    {
        $this->authorize('access-mitra');
        $mitra = $request->user();

        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.admin_product_id' => 'required|integer',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.selected' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $itemRows = collect($data['items']);
        $hasSelectionFlag = $itemRows->contains(function (array $item) {
            return array_key_exists('selected', $item);
        });

        $items = $itemRows
            ->when($hasSelectionFlag, function ($rows) {
                return $rows->filter(function (array $item) {
                    return (bool) ($item['selected'] ?? false);
                });
            })
            ->map(function (array $item) {
                return [
                    'admin_product_id' => (int) $item['admin_product_id'],
                    'qty' => (int) $item['qty'],
                ];
            })
            ->groupBy('admin_product_id')
            ->map(function ($group, $adminProductId) {
                return [
                    'admin_product_id' => (int) $adminProductId,
                    'qty' => (int) $group->sum('qty'),
                ];
            })
            ->values()
            ->all();

        if (count($items) === 0) {
            throw ValidationException::withMessages([
                'items' => 'Pilih minimal satu produk untuk diajukan ke pengadaan.',
            ]);
        }

        return DB::transaction(function () use ($request, $mitra, $data, $items) {
            $hasPaymentColumns = $this->hasPaymentColumns();
            $hasWarehousesTable = Schema::hasTable('warehouses');
            $hasWarehouseData = $hasWarehousesTable
                ? DB::table('warehouses')->exists()
                : false;

            // create order draft
            $orderPayload = [
                'mitra_id' => $mitra->id,
                'total_amount' => 0,
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($hasPaymentColumns) {
                $orderPayload['payment_status'] = 'unpaid';
                $orderPayload['payment_method'] = null;
                $orderPayload['paid_amount'] = null;
                $orderPayload['payment_proof_url'] = null;
                $orderPayload['payment_submitted_at'] = null;
                $orderPayload['payment_note'] = null;
            }

            $orderId = DB::table('admin_orders')->insertGetId($orderPayload);

            $total = 0;

            foreach ($items as $it) {
                $p = DB::table('admin_products as product')
                    ->where('product.id', $it['admin_product_id'])
                    ->lockForUpdate()
                    ->first([
                        'product.id',
                        'product.name',
                        'product.price',
                        'product.unit',
                        'product.min_order_qty',
                        'product.stock_qty',
                        'product.is_active',
                        'product.warehouse_id',
                    ]);

                if (!$p || !$p->is_active) {
                    throw ValidationException::withMessages(['product' => 'Admin product tidak valid/aktif']);
                }

                if ($hasWarehouseData) {
                    if (empty($p->warehouse_id)) {
                        throw ValidationException::withMessages([
                            'product' => "Produk {$p->name} belum terhubung ke gudang admin.",
                        ]);
                    }

                    $warehouse = DB::table('warehouses')
                        ->where('id', (int) $p->warehouse_id)
                        ->lockForUpdate()
                        ->first(['id', 'is_active']);

                    if (! $warehouse) {
                        throw ValidationException::withMessages([
                            'product' => "Gudang untuk produk {$p->name} tidak ditemukan.",
                        ]);
                    }

                    if (! (bool) $warehouse->is_active) {
                        throw ValidationException::withMessages([
                            'product' => "Gudang untuk produk {$p->name} sedang nonaktif.",
                        ]);
                    }
                }

                if ((int)$it['qty'] < (int)$p->min_order_qty) {
                    throw ValidationException::withMessages([
                        'qty' => "Qty minimal untuk {$p->name} adalah {$p->min_order_qty}"
                    ]);
                }

                if ((int)$p->stock_qty < (int)$it['qty']) {
                    throw ValidationException::withMessages([
                        'stock' => "Stock admin product {$p->name} tidak cukup"
                    ]);
                }

                $line = (float)$p->price * (int)$it['qty'];
                $total += $line;

                DB::table('admin_order_items')->insert([
                    'admin_order_id' => $orderId,
                    'admin_product_id' => $p->id,
                    'product_name' => $p->name,
                    'price_per_unit' => $p->price,
                    'unit' => trim((string) ($p->unit ?? '')) !== '' ? strtolower((string) $p->unit) : 'kg',
                    'qty' => $it['qty'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // reserve stock (simple approach)
                DB::table('admin_products')->where('id', $p->id)->update([
                    'stock_qty' => (int)$p->stock_qty - (int)$it['qty'],
                    'updated_at' => now(),
                ]);
            }

            DB::table('admin_orders')->where('id', $orderId)->update([
                'total_amount' => $total,
                'updated_at' => now(),
            ]);

            $this->statusLogger->log(
                $orderId,
                null,
                'pending',
                (int) $mitra->id,
                (string) $mitra->role,
                'Order pengadaan dibuat oleh mitra'
            );

            if ($this->shouldReturnJson($request)) {
                return $this->apiSuccess(
                    [
                        'admin_order_id' => $orderId,
                        'total_amount' => $total,
                        'status' => 'pending',
                    ],
                    "Order pengadaan #{$orderId} berhasil dibuat.",
                    201
                );
            }

            return redirect()
                ->to(route('mitra.procurement.show', ['orderId' => $orderId]) . '#pembayaran-pengadaan')
                ->with('success', "Order pengadaan #{$orderId} berhasil dibuat. Lanjutkan pembayaran agar admin bisa memproses pengiriman.");
        });
    }

    public function show(Request $request, int $orderId)
    {
        $this->authorize('access-mitra');
        $mitra = $request->user();
        $hasPaymentColumns = $this->hasPaymentColumns();
        $walletSummary = $this->walletSnapshot((int) $mitra->id);

        $orderQuery = DB::table('admin_orders')
            ->where('id', $orderId)
            ->where('mitra_id', $mitra->id);

        $selectColumns = ['id', 'mitra_id', 'total_amount', 'status', 'notes', 'created_at', 'updated_at'];
        if ($hasPaymentColumns) {
            $selectColumns = array_merge($selectColumns, [
                'payment_status',
                'payment_method',
                'paid_amount',
                'payment_proof_url',
                'payment_submitted_at',
                'payment_verified_at',
                'payment_note',
            ]);
        }

        $order = $orderQuery->first($selectColumns);

        abort_unless($order, 403);

        $items = DB::table('admin_order_items')
            ->where('admin_order_id', $orderId)
            ->orderBy('id')
            ->get(['id', 'product_name', 'price_per_unit', 'unit', 'qty', 'created_at']);

        $stockSettlement = null;
        if (Schema::hasTable('procurement_stock_settlements')) {
            $stockSettlement = DB::table('procurement_stock_settlements as settlement')
                ->leftJoin('users as actor', 'actor.id', '=', 'settlement.settled_by_user_id')
                ->where('settlement.admin_order_id', $orderId)
                ->first([
                    'settlement.admin_order_id',
                    'settlement.line_count',
                    'settlement.total_qty',
                    'settlement.settled_at',
                    'settlement.settled_by_role',
                    'actor.name as settled_by_name',
                ]);
        }

        $statusHistory = collect();
        if (Schema::hasTable('admin_order_status_histories')) {
            $statusHistory = DB::table('admin_order_status_histories')
                ->leftJoin('users', 'users.id', '=', 'admin_order_status_histories.actor_user_id')
                ->where('admin_order_status_histories.admin_order_id', $orderId)
                ->orderBy('admin_order_status_histories.id')
                ->get([
                    'admin_order_status_histories.from_status',
                    'admin_order_status_histories.to_status',
                    'admin_order_status_histories.note',
                    'admin_order_status_histories.created_at',
                    'users.name as actor_name',
                    'admin_order_status_histories.actor_role',
                ]);
        }

        if ($this->shouldReturnJson($request)) {
            return $this->apiSuccess([
                'order' => $order,
                'items' => $items,
                'stock_settlement' => $stockSettlement,
                'status_history' => $statusHistory,
            ], "Detail order pengadaan #{$orderId} berhasil diambil.");
        }

        return view('mitra.procurement.show', [
            'order' => $order,
            'items' => $items,
            'stockSettlement' => $stockSettlement,
            'statusHistory' => $statusHistory,
            'hasPaymentColumns' => $hasPaymentColumns,
            'walletSummary' => $walletSummary,
        ]);
    }

    public function cancelOrder(Request $request, int $orderId)
    {
        $this->authorize('access-mitra');
        $mitra = $request->user();

        $data = $request->validate([
            'note' => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($request, $mitra, $orderId, $data) {
            $hasPaymentColumns = $this->hasPaymentColumns();
            $order = DB::table('admin_orders')
                ->where('id', $orderId)
                ->where('mitra_id', $mitra->id)
                ->lockForUpdate()
                ->first($hasPaymentColumns
                    ? ['id', 'status', 'payment_status']
                    : ['id', 'status']);

            abort_unless($order, 403);

            $currentStatus = (string) $order->status;
            $cancelableStatuses = ['pending', 'approved'];
            if (! in_array($currentStatus, $cancelableStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => 'Order hanya bisa dibatalkan saat status pending atau approved.',
                ]);
            }

            if (! $this->statusTransition->canTransition($currentStatus, 'cancelled')) {
                throw ValidationException::withMessages([
                    'status' => 'Transisi status ke cancelled tidak diizinkan.',
                ]);
            }

            $this->orderCancellationService->assertCancellablePaymentState($order, $hasPaymentColumns);
            $this->orderCancellationService->restoreReservedStock($orderId);

            DB::table('admin_orders')
                ->where('id', $orderId)
                ->update([
                    'status' => 'cancelled',
                    'updated_at' => now(),
                ]);

            $this->statusLogger->log(
                (int) $orderId,
                $currentStatus,
                'cancelled',
                (int) $mitra->id,
                (string) $mitra->role,
                $data['note'] ?? 'Order dibatalkan oleh mitra'
            );

            return $this->responseForUi(
                $request,
                "Order pengadaan #{$orderId} berhasil dibatalkan.",
                [
                    'admin_order_id' => (int) $orderId,
                    'status' => 'cancelled',
                ]
            );
        });
    }

    public function submitPayment(Request $request, int $orderId)
    {
        $this->authorize('access-mitra');
        $mitra = $request->user();

        if (! $this->hasPaymentColumns()) {
            if ($this->shouldReturnJson($request)) {
                throw ValidationException::withMessages([
                    'payment' => 'Fitur pembayaran pengadaan belum aktif. Jalankan migration terbaru.',
                ]);
            }

            return back()->withErrors(['payment' => 'Fitur pembayaran pengadaan belum aktif. Jalankan migration terbaru.']);
        }

        $data = $request->validate([
            'payment_method' => 'required|in:bank_transfer,wallet',
            'paid_amount' => 'required|numeric|min:1',
            'payment_note' => 'nullable|string|max:255',
            'payment_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ]);

        return DB::transaction(function () use ($request, $mitra, $orderId, $data) {
            $order = DB::table('admin_orders')
                ->where('id', $orderId)
                ->where('mitra_id', $mitra->id)
                ->lockForUpdate()
                ->first([
                    'id',
                    'status',
                    'total_amount',
                    'payment_status',
                    'payment_proof_url',
                ]);

            abort_unless($order, 403);

            $orderStatus = strtolower(trim((string) ($order->status ?? '')));
            $paymentStatus = strtolower(trim((string) ($order->payment_status ?? '')));

            if (! in_array($orderStatus, ['pending', 'approved', 'processing'], true)) {
                throw ValidationException::withMessages([
                    'payment' => 'Pembayaran hanya dapat diajukan saat order masih diproses.',
                ]);
            }

            if (! in_array($paymentStatus, ['unpaid', 'rejected'], true)) {
                throw ValidationException::withMessages([
                    'payment' => 'Pembayaran sudah diajukan atau telah diverifikasi.',
                ]);
            }

            $paymentMethod = strtolower(trim((string) $data['payment_method']));
            $totalAmount = round((float) ($order->total_amount ?? 0), 2);
            $paidAmount = round((float) $data['paid_amount'], 2);
            if ($totalAmount <= 0) {
                throw ValidationException::withMessages([
                    'payment' => 'Total order tidak valid untuk proses pembayaran.',
                ]);
            }

            if ($paymentMethod === 'bank_transfer' && $paidAmount !== $totalAmount) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'Pembayaran transfer wajib sama dengan total tagihan order.',
                ]);
            }

            if ($paymentMethod === 'wallet') {
                if (! $this->canUseWalletLedger()) {
                    $walletMessage = $this->isFinanceDemoModeEnabled()
                        ? 'Pembayaran saldo belum tersedia. Hubungi admin untuk mengaktifkan ledger wallet.'
                        : 'Pembayaran saldo pengadaan sedang dinonaktifkan pada mode demo keuangan.';

                    throw ValidationException::withMessages([
                        'payment_method' => $walletMessage,
                    ]);
                }

                if ($paidAmount !== $totalAmount) {
                    throw ValidationException::withMessages([
                        'paid_amount' => 'Pembayaran via saldo wajib sama dengan total tagihan order.',
                    ]);
                }

                $adminReceiverId = $this->resolveAdminReceiverUserId();
                if ($adminReceiverId <= 0) {
                    throw ValidationException::withMessages([
                        'payment_method' => 'Akun admin penerima pembayaran belum tersedia.',
                    ]);
                }

                $this->lockUsersForWalletMutation([(int) $mitra->id, $adminReceiverId]);

                $walletBalance = (float) DB::table('wallet_transactions')
                    ->where('wallet_id', (int) $mitra->id)
                    ->sum('amount');

                $reservedWithdrawAmount = 0.0;
                if (Schema::hasTable('withdraw_requests')) {
                    $reservedWithdrawRows = DB::table('withdraw_requests')
                        ->where('user_id', (int) $mitra->id)
                        ->whereIn('status', ['pending', 'approved'])
                        ->lockForUpdate()
                        ->get(['amount']);
                    $reservedWithdrawAmount = (float) $reservedWithdrawRows->sum('amount');
                }
                $availableBalance = round(max(0.0, $walletBalance - $reservedWithdrawAmount), 2);

                if ($availableBalance < $totalAmount) {
                    throw ValidationException::withMessages([
                        'paid_amount' => 'Saldo tidak cukup untuk membayar order pengadaan ini.',
                    ]);
                }

                $this->ensureWalletLedgerMutation([
                    'wallet_id' => (int) $mitra->id,
                    'amount' => -1 * $totalAmount,
                    'transaction_type' => 'procurement_payment_wallet',
                    'idempotency_key' => "procurement:order:{$orderId}:wallet:{$mitra->id}:payment",
                    'reference_order_id' => null,
                    'reference_withdraw_id' => null,
                    'description' => "Pembayaran order pengadaan #{$orderId} via saldo wallet",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->ensureWalletLedgerMutation([
                    'wallet_id' => $adminReceiverId,
                    'amount' => $totalAmount,
                    'transaction_type' => 'procurement_income',
                    'idempotency_key' => "procurement:order:{$orderId}:wallet:{$adminReceiverId}:income",
                    'reference_order_id' => null,
                    'reference_withdraw_id' => null,
                    'description' => "Pembayaran order pengadaan mitra #{$orderId} via saldo wallet",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $existingProofPath = trim((string) ($order->payment_proof_url ?? ''));
                if ($existingProofPath !== '') {
                    Storage::disk('public')->delete($existingProofPath);
                }

                $orderUpdate = [
                    'payment_status' => 'paid',
                    'payment_method' => 'wallet',
                    'paid_amount' => $totalAmount,
                    'payment_proof_url' => null,
                    'payment_submitted_at' => now(),
                    'payment_note' => trim((string) ($data['payment_note'] ?? '')) ?: 'Pembayaran saldo wallet terkonfirmasi otomatis.',
                    'payment_verified_at' => now(),
                    'payment_verified_by' => null,
                    'updated_at' => now(),
                ];
                if (in_array($orderStatus, ['pending', 'approved'], true)) {
                    $orderUpdate['status'] = 'processing';
                }

                DB::table('admin_orders')
                    ->where('id', $orderId)
                    ->update($orderUpdate);

                if (in_array($orderStatus, ['pending', 'approved'], true)) {
                    $this->statusLogger->log(
                        (int) $orderId,
                        $orderStatus,
                        'processing',
                        (int) $mitra->id,
                        (string) $mitra->role,
                        'Status otomatis ke processing setelah pembayaran saldo wallet berhasil.'
                    );
                }

                if ($this->shouldReturnJson($request)) {
                    return $this->apiSuccess([
                        'admin_order_id' => (int) $orderId,
                        'payment_status' => 'paid',
                        'payment_method' => 'wallet',
                        'status' => in_array($orderStatus, ['pending', 'approved'], true) ? 'processing' : $orderStatus,
                    ], "Pembayaran saldo order pengadaan #{$orderId} berhasil diproses.");
                }

                return redirect()
                    ->route('mitra.procurement.show', ['orderId' => $orderId])
                    ->with('success', "Pembayaran saldo order pengadaan #{$orderId} berhasil diproses.");
            }

            $proofPath = (string) ($order->payment_proof_url ?? '');
            if ($request->hasFile('payment_proof')) {
                if ($proofPath !== '') {
                    Storage::disk('public')->delete($proofPath);
                }
                $proofPath = $request->file('payment_proof')->store('procurement-payments', 'public');
            }

            DB::table('admin_orders')
                ->where('id', $orderId)
                ->update([
                    'payment_status' => 'pending_verification',
                    'payment_method' => (string) $paymentMethod,
                    'paid_amount' => $paidAmount,
                    'payment_proof_url' => $proofPath !== '' ? $proofPath : null,
                    'payment_submitted_at' => now(),
                    'payment_note' => trim((string) ($data['payment_note'] ?? '')) ?: null,
                    'payment_verified_at' => null,
                    'payment_verified_by' => null,
                    'updated_at' => now(),
                ]);

            if ($this->shouldReturnJson($request)) {
                return $this->apiSuccess([
                    'admin_order_id' => (int) $orderId,
                    'payment_status' => 'pending_verification',
                ], "Pembayaran order pengadaan #{$orderId} berhasil diajukan untuk verifikasi admin.");
            }

            return redirect()
                ->route('mitra.procurement.show', ['orderId' => $orderId])
                ->with('success', "Pembayaran order pengadaan #{$orderId} berhasil diajukan untuk verifikasi admin.");
        });
    }

    public function confirmReceived(Request $request, int $orderId)
    {
        $this->authorize('access-mitra');
        $mitra = $request->user();

        $data = $request->validate([
            'note' => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($request, $mitra, $orderId, $data) {
            $selectColumns = ['id', 'status'];
            if ($this->hasPaymentColumns()) {
                $selectColumns[] = 'payment_status';
            }

            $order = DB::table('admin_orders')
                ->where('id', $orderId)
                ->where('mitra_id', $mitra->id)
                ->lockForUpdate()
                ->first($selectColumns);

            abort_unless($order, 403);

            $currentStatus = strtolower(trim((string) ($order->status ?? '')));
            if ($currentStatus !== 'shipped') {
                throw ValidationException::withMessages([
                    'status' => 'Konfirmasi diterima hanya bisa dilakukan saat status order shipped.',
                ]);
            }

            if (
                $this->hasPaymentColumns()
                && strtolower(trim((string) ($order->payment_status ?? 'unpaid'))) !== 'paid'
            ) {
                throw ValidationException::withMessages([
                    'payment' => 'Order belum memiliki pembayaran terverifikasi paid.',
                ]);
            }

            if (! $this->statusTransition->canTransition($currentStatus, 'delivered')) {
                throw ValidationException::withMessages([
                    'status' => 'Transisi status ke delivered tidak diizinkan.',
                ]);
            }

            DB::table('admin_orders')
                ->where('id', $orderId)
                ->update([
                    'status' => 'delivered',
                    'updated_at' => now(),
                ]);

            $settlementResult = $this->stockSettlementService->settleDeliveredOrder(
                adminOrderId: $orderId,
                actorUserId: (int) $mitra->id,
                actorRole: (string) $mitra->role
            );

            $this->statusLogger->log(
                (int) $orderId,
                $currentStatus,
                'delivered',
                (int) $mitra->id,
                (string) $mitra->role,
                $data['note'] ?? 'Barang diterima dan dikonfirmasi oleh mitra'
            );

            if ($this->shouldReturnJson($request)) {
                return $this->apiSuccess(
                    [
                        'admin_order_id' => (int) $orderId,
                        'status' => 'delivered',
                        'stock_settlement' => $settlementResult,
                    ],
                    "Order pengadaan #{$orderId} berhasil dikonfirmasi diterima."
                );
            }

            return redirect()
                ->route('mitra.procurement.show', ['orderId' => $orderId])
                ->with('procurement_received_notice', 'Barang diterima, Perika produk di Menu!!');
        });
    }
}
