<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockMitraMarketplace
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && strtolower(trim((string) $user->role)) === 'mitra') {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Akun mitra tidak dapat mengakses marketplace umum.',
                ], Response::HTTP_FORBIDDEN);
            }

            return redirect()
                ->route('mitra.dashboard')
                ->with('status', 'Akses marketplace umum tidak tersedia untuk akun mitra.');
        }

        return $next($request);
    }
}
