<?php

namespace App\Http\Controllers;

use App\Services\OrderTransferPaymentService;
use App\Http\Controllers\Concerns\ApiResponse;
use Illuminate\Http\Request;

class P2PPaymentController extends Controller
{
    use ApiResponse;

    public function __construct(protected OrderTransferPaymentService $transferPayment) {}

    public function uploadProof(Request $request, int $orderId)
    {
        $user = $request->user();

        $request->validate([
            'proof' => 'required|file|mimes:jpg,jpeg,png,pdf,webp|max:4096',
            'paid_amount' => 'required|numeric|min:1',
            'payment_method' => 'nullable|string',
        ]);

        $payload = $this->transferPayment->submit(
            $user,
            $orderId,
            $request->file('proof'),
            (float) $request->input('paid_amount'),
            $request->input('payment_method')
        );

        if ($request->expectsJson()) {
            return $this->apiSuccess($payload, 'Bukti pembayaran berhasil diupload. Menunggu verifikasi seller.');
        }

        return back()->with('status', 'Bukti pembayaran berhasil diupload. Menunggu verifikasi seller.');
    }
}
