<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\RoleRedirector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    private const SESSION_REDIRECT_KEY = 'auth.redirect_to';

    public function redirectToGoogle(Request $request): RedirectResponse
    {
        $requestedRedirect = $this->resolveSafeRedirectTarget((string) $request->query('redirect', ''));
        if ($requestedRedirect !== null) {
            $request->session()->put(self::SESSION_REDIRECT_KEY, $requestedRedirect);
        } else {
            $request->session()->forget(self::SESSION_REDIRECT_KEY);
        }

        if (! $this->isGoogleConfigReady()) {
            $loginParams = $requestedRedirect !== null ? ['redirect' => $requestedRedirect] : [];

            return redirect()->route('login', $loginParams)->withErrors([
                'email' => 'Konfigurasi Google login belum lengkap. Isi GOOGLE_CLIENT_ID dan GOOGLE_CLIENT_SECRET di file .env.',
            ]);
        }

        return Socialite::driver('google')
            ->redirectUrl($this->resolveCallbackUrl())
            ->redirect();
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        $requestedRedirect = $this->resolveSafeRedirectTarget(
            (string) $request->session()->pull(self::SESSION_REDIRECT_KEY, '')
        );

        if (! $this->isGoogleConfigReady()) {
            return redirect()->route('login')->withErrors([
                'email' => 'Konfigurasi Google login belum lengkap. Silakan hubungi admin aplikasi.',
            ]);
        }

        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl($this->resolveCallbackUrl())
                ->user();
        } catch (Throwable $e) {
            return redirect()->route('login')->withErrors([
                'email' => 'Login Google gagal. Silakan coba lagi.',
            ]);
        }

        $email = $googleUser->getEmail();

        if (! $email) {
            return redirect()->route('login')->withErrors([
                'email' => 'Akun Google tidak memiliki email yang valid.',
            ]);
        }

        $user = User::query()
            ->where('google_id', $googleUser->getId())
            ->orWhere('email', $email)
            ->first();

        if ($user) {
            $user->forceFill([
                'name' => $googleUser->getName() ?: $user->name,
                'google_id' => $googleUser->getId(),
                'google_avatar' => $googleUser->getAvatar(),
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();
        } else {
            $user = User::create([
                'name' => $googleUser->getName() ?: 'Google User',
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
                'role' => 'consumer',
                'google_id' => $googleUser->getId(),
                'google_avatar' => $googleUser->getAvatar(),
                'email_verified_at' => now(),
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $target = app(RoleRedirector::class)->postLoginPathFor($user);
        if ($target !== '/dashboard') {
            $request->session()->forget('url.intended');
            return redirect()->to($target);
        }

        return redirect()->intended($requestedRedirect ?? $target);
    }

    private function isGoogleConfigReady(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    private function resolveCallbackUrl(): string
    {
        return (string) config('services.google.redirect');
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
