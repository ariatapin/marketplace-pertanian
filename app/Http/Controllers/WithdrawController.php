<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Models\User;
use App\Services\WithdrawBankAccountService;
use App\Services\WithdrawPolicyService;
use App\Services\WalletService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WithdrawController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected WalletService $wallet,
        protected WithdrawBankAccountService $withdrawBankAccounts,
        protected WithdrawPolicyService $withdrawPolicy
    ) {}

    public function requestWithdraw(Request $request)
    {
        $user = $request->user();
        $policy = $this->withdrawPolicy->evaluate($user);
        if (! $policy['allowed']) {
            if ($request->expectsJson() || $request->is('api/*')) {
                throw new AuthorizationException((string) $policy['message']);
            }

            return back()->with('error', (string) $policy['message']);
        }

        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        return DB::transaction(function () use ($request, $user) {
            DB::table('users')
                ->where('id', (int) $user->id)
                ->lockForUpdate()
                ->first(['id']);

            $admin = User::query()
                ->whereNormalizedRole('admin')
                ->orderBy('id')
                ->first(['id']);
            $adminProfile = $admin ? DB::table('admin_profiles')->where('user_id', $admin->id)->first() : null;
            $minWithdraw = (float)($adminProfile?->min_withdraw_amount ?? 0);

            $amount = round((float) $request->amount, 2);

            if ($amount < $minWithdraw) {
                throw ValidationException::withMessages(['amount' => "Minimal withdraw {$minWithdraw}"]);
            }

            $bank = $this->withdrawBankAccounts->snapshot((int) $user->id);
            if (! $bank['complete']) {
                throw ValidationException::withMessages([
                    'bank_profile' => 'Lengkapi data rekening terlebih dahulu (nama bank, nomor rekening, nama pemilik).',
                ]);
            }

            $recentDuplicate = DB::table('withdraw_requests')
                ->where('user_id', (int) $user->id)
                ->where('status', 'pending')
                ->where('amount', $amount)
                ->where('bank_name', $bank['bank_name'])
                ->where('account_number', $bank['account_number'])
                ->where('account_holder', $bank['account_holder'])
                ->where('created_at', '>=', now()->subMinutes(5))
                ->lockForUpdate()
                ->first(['id', 'status']);
            if ($recentDuplicate) {
                return $this->responseForUi($request, 'Permintaan withdraw serupa sudah dibuat sebelumnya.', [
                    'withdraw_request_id' => (int) $recentDuplicate->id,
                    'status' => (string) $recentDuplicate->status,
                    'idempotency_hit' => true,
                ], 200);
            }

            $balance = $this->wallet->getBalance($user->id);
            $reservedRows = DB::table('withdraw_requests')
                ->where('user_id', (int) $user->id)
                ->whereIn('status', ['pending', 'approved'])
                ->lockForUpdate()
                ->get(['amount']);
            $reservedAmount = (float) $reservedRows->sum('amount');
            $availableBalance = max(0, round($balance - $reservedAmount, 2));

            if ($amount > $availableBalance) {
                throw ValidationException::withMessages(['amount' => 'Saldo tidak cukup']);
            }

            $id = DB::table('withdraw_requests')->insertGetId([
                'user_id' => $user->id,
                'amount' => $amount,
                'bank_name' => $bank['bank_name'],
                'account_number' => $bank['account_number'],
                'account_holder' => $bank['account_holder'],
                'status' => 'pending',
                'processed_by' => null,
                'processed_at' => null,
                'transfer_proof_url' => null,
                'transfer_reference' => null,
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->responseForUi($request, 'Permintaan withdraw berhasil dibuat.', [
                'withdraw_request_id' => $id, 
                'status' => 'pending',
                'balance_before' => $balance,
                'reserved_before' => $reservedAmount,
                'available_before' => $availableBalance,
            ], 201);
        });
    }

    private function responseForUi(Request $request, string $message, array $payload = [], int $status = 200)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->apiSuccess($payload, $message, $status);
        }

        return back()->with('status', $message);
    }
}
