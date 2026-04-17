<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Services\DisputeService;
use Illuminate\Http\Request;

class DisputeController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DisputeService $disputeService
    ) {}

    public function store(Request $request, int $orderId)
    {
        $this->authorize('access-consumer');

        $data = $request->validate([
            'category' => 'required|string|in:not_received,damaged,wrong_item,delayed_shipping,other',
            'description' => 'required|string|max:2000',
            'evidence_urls' => 'nullable|array|max:5',
            'evidence_urls.*' => 'nullable|url|max:500',
        ]);

        $payload = $this->disputeService->openByBuyer(
            buyer: $request->user(),
            orderId: $orderId,
            payload: $data
        );

        return $this->responseForUi(
            $request,
            "Sengketa untuk order #{$orderId} berhasil dikirim dan menunggu review admin.",
            $payload,
            201
        );
    }

    private function responseForUi(Request $request, string $message, array $payload = [], int $status = 200)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->apiSuccess($payload, $message, $status);
        }

        return back()->with('status', $message);
    }
}

