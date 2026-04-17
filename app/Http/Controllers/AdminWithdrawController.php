<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Concerns\DetectsJsonRequest;
use App\Support\Concerns\GuardsFinanceDemoMode;
use App\Support\Concerns\HandlesWalletLedgerMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AdminWithdrawController extends Controller
{
    use ApiResponse;
    use DetectsJsonRequest;
    use GuardsFinanceDemoMode;
    use HandlesWalletLedgerMutation;

    private function responseForUi(Request $request, string $message, array $payload = [])
    {
        if ($this->shouldReturnJson($request)) {
            return $this->apiSuccess($payload, $message);
        }

        return back()->with('status', $message);
    }

    private function hasWithdrawPaidAuditColumns(): bool
    {
        return Schema::hasTable('withdraw_requests')
            && Schema::hasColumn('withdraw_requests', 'paid_by')
            && Schema::hasColumn('withdraw_requests', 'paid_at');
    }

    public function approve(Request $request, int $withdrawId)
    {
        $this->authorize('access-admin');
        $admin = $request->user();

        return DB::transaction(function () use ($request, $admin, $withdrawId) {
            $w = DB::table('withdraw_requests')->where('id', $withdrawId)->lockForUpdate()->first();
            if (!$w) throw ValidationException::withMessages(['withdraw' => 'Withdraw tidak ditemukan']);
            if ($w->status !== 'pending') throw ValidationException::withMessages(['status' => 'Bukan pending']);

            DB::table('withdraw_requests')->where('id', $withdrawId)->update([
                'status' => 'approved',
                'processed_by' => $admin->id,
                'processed_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->responseForUi(
                $request,
                "Withdraw #{$withdrawId} disetujui.",
                ['status' => 'approved']
            );
        });
    }

    public function markPaid(Request $request, int $withdrawId)
    {
        $this->authorize('access-admin');
        $this->assertFinanceDemoModeEnabled(
            errorKey: 'withdraw',
            message: 'Pencairan withdraw demo sedang dinonaktifkan.'
        );

        $admin = $request->user();

        $data = $request->validate([
            'transfer_reference' => 'nullable|string|max:120',
            'transfer_proof_url' => 'nullable|string',
        ]);
        $transferReference = trim((string) ($data['transfer_reference'] ?? ''));
        $transferProofUrl = trim((string) ($data['transfer_proof_url'] ?? ''));
        if ($transferReference === '' && $transferProofUrl === '') {
            throw ValidationException::withMessages([
                'transfer_reference' => 'Isi minimal referensi transfer atau URL bukti transfer.',
            ]);
        }
        if (! $this->hasWithdrawPaidAuditColumns()) {
            throw ValidationException::withMessages([
                'withdraw' => 'Kolom audit paid belum siap. Jalankan migrasi terbaru.',
            ]);
        }

        return DB::transaction(function () use ($request, $admin, $withdrawId, $transferReference, $transferProofUrl) {

            $w = DB::table('withdraw_requests')->where('id', $withdrawId)->lockForUpdate()->first();
            if (!$w) throw ValidationException::withMessages(['withdraw' => 'Withdraw tidak ditemukan']);
            if ((string) $w->status !== 'approved') {
                throw ValidationException::withMessages(['status' => 'Withdraw harus berstatus approved sebelum ditandai paid.']);
            }

            if (! $this->hasWalletLedgerColumns()) {
                throw ValidationException::withMessages([
                    'wallet' => 'Ledger wallet belum siap. Jalankan migrasi terbaru.',
                ]);
            }

            $lockIds = collect([(int) $admin->id, (int) $w->user_id])
                ->unique()
                ->sort()
                ->values()
                ->all();
            DB::table('users')
                ->whereIn('id', $lockIds)
                ->lockForUpdate()
                ->get(['id']);

            $amount = (float) $w->amount;
            $adminBalance = (float) DB::table('wallet_transactions')
                ->where('wallet_id', (int) $admin->id)
                ->sum('amount');
            if ($adminBalance < $amount) {
                throw ValidationException::withMessages([
                    'admin_balance' => 'Saldo admin tidak cukup untuk melakukan pencairan ini.',
                ]);
            }

            $userBalance = (float) DB::table('wallet_transactions')
                ->where('wallet_id', (int) $w->user_id)
                ->sum('amount');
            if ($userBalance < $amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Saldo wallet user tidak cukup untuk dicairkan.',
                ]);
            }

            // Deduct saldo user saat PAID
            $this->ensureWalletLedgerMutation([
                'wallet_id' => $w->user_id,
                'amount' => -1 * $amount,
                'transaction_type' => 'withdrawal',
                'idempotency_key' => "withdraw:{$withdrawId}:wallet:{$w->user_id}:withdrawal",
                'reference_order_id' => null,
                'reference_withdraw_id' => $withdrawId,
                'description' => 'Withdraw payout',
                'created_at' => now(),
                'updated_at' => now(),
            ], 'Ledger wallet tidak konsisten saat memproses withdraw.');

            // Deduct saldo admin sebagai sumber dana pencairan.
            $this->ensureWalletLedgerMutation([
                'wallet_id' => $admin->id,
                'amount' => -1 * $amount,
                'transaction_type' => 'admin_payout',
                'idempotency_key' => "withdraw:{$withdrawId}:wallet:{$admin->id}:admin_payout",
                'reference_order_id' => null,
                'reference_withdraw_id' => $withdrawId,
                'description' => "Pencairan withdraw #{$withdrawId}",
                'created_at' => now(),
                'updated_at' => now(),
            ], 'Ledger wallet tidak konsisten saat memproses withdraw.');

            DB::table('withdraw_requests')->where('id', $withdrawId)->update([
                'status' => 'paid',
                'paid_by' => $admin->id,
                'paid_at' => now(),
                'transfer_reference' => $transferReference !== '' ? $transferReference : null,
                'transfer_proof_url' => $transferProofUrl !== '' ? $transferProofUrl : null,
                'updated_at' => now(),
            ]);

            return $this->responseForUi(
                $request,
                "Withdraw #{$withdrawId} ditandai paid.",
                [
                    'status' => 'paid',
                    'admin_balance_after' => max(0, $adminBalance - $amount),
                ]
            );
        });
    }

    public function reject(Request $request, int $withdrawId)
    {
        $this->authorize('access-admin');
        $admin = $request->user();

        return DB::transaction(function () use ($request, $admin, $withdrawId) {
            $w = DB::table('withdraw_requests')->where('id', $withdrawId)->lockForUpdate()->first();
            if (!$w) throw ValidationException::withMessages(['withdraw' => 'Withdraw tidak ditemukan']);
            if ($w->status !== 'pending') throw ValidationException::withMessages(['status' => 'Bukan pending']);

            DB::table('withdraw_requests')->where('id', $withdrawId)->update([
                'status' => 'rejected',
                'processed_by' => $admin->id,
                'processed_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->responseForUi(
                $request,
                "Withdraw #{$withdrawId} ditolak.",
                ['status' => 'rejected']
            );
        });
    }

}
