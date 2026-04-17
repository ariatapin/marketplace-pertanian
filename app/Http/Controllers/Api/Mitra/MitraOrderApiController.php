<?php

namespace App\Http\Controllers\Api\Mitra;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Mitra\MitraOrderWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MitraOrderApiController extends Controller
{
    use ApiResponse;

    private const MITRA_ORDER_SOURCE = 'store_online';
    private function applyMitraOrderScope($query)
    {
        return $query->where('orders.order_source', self::MITRA_ORDER_SOURCE);
    }

    public function __construct(
        protected MitraOrderWorkflowService $workflow
    ) {}

    public function index(Request $request)
    {
        $mitra = $request->user();

        $rows = $this->applyMitraOrderScope(
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
            ->orderByDesc('orders.id')
            ->get();

        return $this->apiSuccess($rows, 'Daftar order mitra berhasil diambil.');
    }

    public function markPacked(Request $request, int $orderId)
    {
        $mitra = $request->user();
        $payload = $this->workflow->markPacked($mitra, $orderId);

        return $this->apiSuccess($payload, 'Order berhasil diubah ke packed.');
    }

    public function markPaid(Request $request, int $orderId)
    {
        $mitra = $request->user();
        $payload = $this->workflow->markPaid($mitra, $orderId);

        return $this->apiSuccess($payload, 'Pembayaran transfer diverifikasi dan order otomatis masuk packing.');
    }

    public function show(Request $request, int $orderId)
    {
        $mitra = $request->user();
        $payload = $this->workflow->detail($mitra, $orderId);

        return $this->apiSuccess($payload, 'Detail order mitra berhasil diambil.');
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

        return $this->apiSuccess($payload, 'Order berhasil diubah ke shipped.');
    }
}
