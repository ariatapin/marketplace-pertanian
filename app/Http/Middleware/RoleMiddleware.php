<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        // Kalau request API (expects JSON), jangan redirect login
        if (!$user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'data' => null,
                    'errors' => null,
                ], 401);
            }

            return redirect()->route('login');
        }

        $normalizedUserRole = strtolower(trim((string) $user->role));
        $normalizedAllowedRoles = array_map(
            static fn ($role) => strtolower(trim((string) $role)),
            $roles
        );

        if (! in_array($normalizedUserRole, $normalizedAllowedRoles, true)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized role.',
                    'data' => null,
                    'errors' => [
                        'role' => $normalizedUserRole,
                        'allowed' => $roles,
                    ],
                ], 403);
            }

            abort(403, 'Unauthorized role.');
        }

        return $next($request);
    }
}
