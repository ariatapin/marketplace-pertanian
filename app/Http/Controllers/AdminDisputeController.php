<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Services\DisputeService;
use App\Services\RefundService;
use Illuminate\Http\Request;

class AdminDisputeController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DisputeService $disputeService,
        protected RefundService $refundService
    ) {}

    public function review(Request $request, int $reportId)
    {
        $this->authorize('access-admin');

        $data = $request->validate([
            'status' => 'required|string|in:under_review,resolved_buyer,resolved_seller,cancelled',
            'resolution' => 'nullable|string|in:refund_full,refund_partial,release_to_seller',
            'refund_amount' => 'nullable|numeric|min:1',
            'resolution_notes' => 'nullable|string|max:2000',
        ]);

        $payload = $this->disputeService->reviewByAdmin(
            admin: $request->user(),
            disputeId: $reportId,
            payload: $data
        );

        return $this->responseForUi(
            $request,
            "Laporan #{$reportId} berhasil diperbarui.",
            $payload
        );
    }

    public function markRefundPaid(Request $request, int $refundId)
    {
        $this->authorize('access-admin');

        $data = $request->validate([
            'refund_reference' => 'nullable|string|max:120',
            'refund_proof_url' => 'nullable|string',
            'notes' => 'nullable|string|max:2000',
        ]);

        $payload = $this->refundService->markPaid(
            refundId: $refundId,
            adminUserId: (int) $request->user()->id,
            transferReference: (string) ($data['refund_reference'] ?? ''),
            refundProofUrl: (string) ($data['refund_proof_url'] ?? ''),
            notes: (string) ($data['notes'] ?? '')
        );

        return $this->responseForUi(
            $request,
            "Refund #{$refundId} berhasil ditandai paid.",
            $payload
        );
    }

    private function responseForUi(Request $request, string $message, array $payload = [])
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->apiSuccess($payload, $message);
        }

        return back()->with('status', $message);
    }
}

