<?php

namespace App\Http\Controllers;

use App\Services\DemoWalletTopupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DemoWalletTopupController extends Controller
{
    public function __construct(
        private readonly DemoWalletTopupService $topupService
    ) {
    }

    /**
     * Topup saldo demo manual untuk akun demo.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1000', 'max:1000000000'],
            'idempotency_key' => ['required', 'string', 'max:160'],
        ]);

        $result = $this->topupService->topup(
            $request->user(),
            (float) $validated['amount'],
            (string) $validated['idempotency_key']
        );

        return back()->with([
            'topup_success' => true,
            'topup_message' => 'Topup Berhasil',
            'topup_balance' => (float) ($result['balance'] ?? 0),
        ]);
    }
}
