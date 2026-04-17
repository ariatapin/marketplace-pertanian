<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Mitra\MitraOrderWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    use ApiResponse;

    private const MITRA_ORDER_SOURCE = 'store_online';
    private function applyMitraOrderScope($query)
    {
        return $query->where('order_source', self::MITRA_ORDER_SOURCE);
    }

    public function __construct(
        protected MitraOrderWorkflowService $workflow
    ) {}

    public function index(Request $request)
    {
        $mitra = $request->user();
        $status = $request->string('status')->toString();
        $paymentStatus = $request->string('payment_status')->toString();
        $keyword = trim($request->string('q')->toString());

        $summary = [
            'total' => 0,
            'pending_payment' => 0,
            'paid' => 0,
            'packed' => 0,
            'shipped' => 0,
            'completed' => 0,
        ];

        $rows = collect();

        if (DB::getSchemaBuilder()->hasTable('orders')) {
            $base = $this->applyMitraOrderScope(
                DB::table('orders')->where('seller_id', $mitra->id)
            );

            $summary['total'] = (clone $base)->count();
            $summary['pending_payment'] = (clone $base)->where('order_status', 'pending_payment')->count();
            $summary['paid'] = (clone $base)->where('order_status', 'paid')->count();
            $summary['packed'] = (clone $base)->where('order_status', 'packed')->count();
            $summary['shipped'] = (clone $base)->where('order_status', 'shipped')->count();
            $summary['completed'] = (clone $base)->where('order_status', 'completed')->count();

            $query = $this->applyMitraOrderScope(
                DB::table('orders')
                    ->leftJoin('users as buyer', 'buyer.id', '=', 'orders.buyer_id')
                    ->where('orders.seller_id', $mitra->id)
            )
                ->select(
                    'orders.id',
                    'orders.order_source',
                    'orders.total_amount',
                    'orders.payment_method',
                    'orders.payment_status',
                    'orders.order_status',
                    'orders.shipping_status',
                    'orders.payment_proof_url',
                    'orders.paid_amount',
                    'orders.resi_number',
                    'orders.created_at',
                    'buyer.name as buyer_name',
                    'buyer.email as buyer_email'
                )
                ->orderByDesc('orders.id');

            if (in_array($status, ['pending_payment', 'paid', 'packed', 'shipped', 'completed', 'cancelled'], true)) {
                $query->where('orders.order_status', $status);
            }

            if (in_array($paymentStatus, ['unpaid', 'paid', 'refunded', 'failed'], true)) {
                $query->where('orders.payment_status', $paymentStatus);
            }

            if ($keyword !== '') {
                $query->where(function ($sub) use ($keyword) {
                    $sub->where('orders.id', 'like', "%{$keyword}%")
                        ->orWhere('buyer.name', 'like', "%{$keyword}%")
                        ->orWhere('buyer.email', 'like', "%{$keyword}%");
                });
            }

            $rows = $query->paginate(15)->withQueryString();
        }

        return view('mitra.orders.index', [
            'rows' => $rows,
            'summary' => $summary,
            'filters' => [
                'status' => $status,
                'payment_status' => $paymentStatus,
                'q' => $keyword,
            ],
        ]);
    }

    public function markPacked(Request $request, int $orderId)
    {
        $mitra = $request->user();
        $payload = $this->workflow->markPacked($mitra, $orderId);

        if ($request->expectsJson()) {
            return $this->apiSuccess($payload, 'Order berhasil diubah ke packed.');
        }

        return back()->with('status', "Order #{$orderId} berhasil diubah ke packed.");
    }

    public function markPaid(Request $request, int $orderId)
    {
        $mitra = $request->user();
        $payload = $this->workflow->markPaid($mitra, $orderId);

        if ($request->expectsJson()) {
            return $this->apiSuccess($payload, 'Pembayaran transfer berhasil diverifikasi dan order otomatis masuk packing.');
        }

        return back()->with('status', "Pembayaran order #{$orderId} diverifikasi. Status order otomatis menjadi PACKED.");
    }

    public function show(Request $request, int $orderId)
    {
        $mitra = $request->user();
        $payload = $this->workflow->detail($mitra, $orderId);

        return view('mitra.orders.show', [
            'order' => $payload['order'],
            'items' => $payload['items'],
            'itemsTotalQty' => (int) ($payload['summary']['total_qty'] ?? 0),
            'itemsTotalAmount' => (float) ($payload['summary']['items_total_amount'] ?? 0),
            'statusHistory' => $payload['status_history'],
        ]);
    }

    public function markShipped(Request $request, int $orderId)
    {
        $mitra = $request->user();

        $request->validate([
            'resi_number' => 'nullable|string|max:120',
        ]);

        $payload = $this->workflow->markShipped(
            $mitra,
            $orderId,
            $request->string('resi_number')->toString()
        );

        if ($request->expectsJson()) {
            return $this->apiSuccess($payload, 'Order berhasil diubah ke shipped.');
        }

        return back()->with('status', "Order #{$orderId} berhasil diubah ke shipped.");
    }
}
