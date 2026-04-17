<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if ((bool) ($user->is_suspended ?? false)) {
            $note = trim((string) ($user->suspension_note ?? ''));
            $isBlocked = preg_match('/^\[BLOCKED\]/i', $note) === 1;
            $message = $isBlocked
                ? 'Akun Anda diblokir. Hubungi admin untuk proses aktivasi ulang.'
                : 'Akun Anda sedang disuspend. Hubungi admin untuk aktivasi ulang.';

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'data' => null,
                    'errors' => [
                        'suspended' => true,
                        'blocked' => $isBlocked,
                    ],
                ], Response::HTTP_FORBIDDEN);
            }

            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()
                ->route('login')
                ->withErrors(['email' => $message]);
        }

        $this->normalizeLegacySellerRole($user);

        return $next($request);
    }

    /**
     * Kompatibilitas role lama: `farmer_seller` diselaraskan ke role `consumer`
     * dengan mode consumer `farmer_seller` yang approved.
     */
    private function normalizeLegacySellerRole(object $user): void
    {
        $normalizedRole = strtolower(trim((string) ($user->role ?? '')));
        if ($normalizedRole !== 'farmer_seller') {
            return;
        }

        DB::table('users')
            ->where('id', (int) $user->id)
            ->update([
                'role' => 'consumer',
                'updated_at' => now(),
            ]);

        if (Schema::hasTable('consumer_profiles')) {
            $profilePayload = [
                'mode' => 'farmer_seller',
                'mode_status' => 'approved',
                'requested_mode' => null,
                'updated_at' => now(),
            ];

            $profileExists = DB::table('consumer_profiles')
                ->where('user_id', (int) $user->id)
                ->exists();

            if ($profileExists) {
                DB::table('consumer_profiles')
                    ->where('user_id', (int) $user->id)
                    ->update($profilePayload);
            } else {
                DB::table('consumer_profiles')->insert($profilePayload + [
                    'user_id' => (int) $user->id,
                    'address' => null,
                    'created_at' => now(),
                ]);
            }
        }

        $user->role = 'consumer';
    }
}
