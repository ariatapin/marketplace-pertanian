<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\DemoUserProvisioner;
use App\Support\RoleRedirector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        if (app()->environment('local')) {
            app(DemoUserProvisioner::class)->ensureUsers();
        }

        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $requestedRedirect = $this->resolveSafeRedirectTarget(
            (string) $request->input('redirect_to', $request->query('redirect', ''))
        );
        $currentUser = Auth::guard('web')->user();
        $requestedEmail = strtolower(trim((string) $request->input('email', '')));

        if ($currentUser) {
            $currentEmail = strtolower(trim((string) ($currentUser->email ?? '')));

            if ($requestedEmail !== '' && $currentEmail !== $requestedEmail) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            } else {
                $target = app(RoleRedirector::class)->pathFor($currentUser);
                return redirect()->to($target);
            }
        }

        $request->authenticate();
        $request->session()->regenerate();

        $user = Auth::guard('web')->user();
        $target = app(RoleRedirector::class)->postLoginPathFor($user);
        // Untuk role dashboard khusus (admin/mitra/affiliate/seller), jangan
        // tertimpa url.intended yang tersisa dari sesi sebelumnya.
        if ($target !== '/dashboard') {
            $request->session()->forget('url.intended');
            return redirect()->to($target);
        }

        return redirect()->intended($requestedRedirect ?? $target);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function resolveSafeRedirectTarget(string $rawTarget): ?string
    {
        $target = trim($rawTarget);
        if ($target === '') {
            return null;
        }

        if (str_starts_with($target, '/')) {
            return str_starts_with($target, '//') ? null : $target;
        }

        if (! preg_match('/^https?:\/\//i', $target)) {
            return null;
        }

        $parsedTarget = parse_url($target);
        $parsedAppUrl = parse_url((string) config('app.url', ''));
        if (! is_array($parsedTarget) || ! is_array($parsedAppUrl)) {
            return null;
        }

        $targetHost = strtolower((string) ($parsedTarget['host'] ?? ''));
        $appHost = strtolower((string) ($parsedAppUrl['host'] ?? ''));
        if ($targetHost === '' || $appHost === '' || $targetHost !== $appHost) {
            return null;
        }

        $path = (string) ($parsedTarget['path'] ?? '/');
        $query = isset($parsedTarget['query']) ? ('?' . $parsedTarget['query']) : '';
        $fragment = isset($parsedTarget['fragment']) ? ('#' . $parsedTarget['fragment']) : '';

        return $path . $query . $fragment;
    }
}
