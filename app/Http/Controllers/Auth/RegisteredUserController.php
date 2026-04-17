<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $requestedRedirect = $this->resolveSafeRedirectTarget(
            (string) $request->input('redirect_to', $request->query('redirect', ''))
        );

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone_number' => ['required', 'string', 'max:20'],
            'province_id' => ['required', 'integer', 'exists:provinces,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $provinceId = (int) $request->integer('province_id');
        $cityId = (int) $request->integer('city_id');
        $districtId = $request->filled('district_id') ? (int) $request->integer('district_id') : null;

        $city = DB::table('cities')
            ->where('id', $cityId)
            ->first(['id', 'province_id', 'lat', 'lng']);
        if (! $city || (int) ($city->province_id ?? 0) !== $provinceId) {
            return back()
                ->withErrors(['city_id' => 'Kota tidak sesuai dengan provinsi yang dipilih.'])
                ->withInput();
        }

        if ($districtId !== null) {
            $districtMatchesCity = DB::table('districts')
                ->where('id', $districtId)
                ->where('city_id', (int) $city->id)
                ->exists();

            if (! $districtMatchesCity) {
                return back()
                    ->withErrors(['district_id' => 'Kecamatan tidak sesuai dengan kota yang dipilih.'])
                    ->withInput();
            }
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'password' => Hash::make($request->password),
            'role' => 'consumer',
            'province_id' => (int) $city->province_id,
            'city_id' => (int) $city->id,
            'district_id' => $districtId,
            'lat' => $city->lat,
            'lng' => $city->lng,
        ]);

        event(new Registered($user));

        Auth::login($user);

        if ($requestedRedirect !== null) {
            return redirect()->to($requestedRedirect);
        }

        return redirect(route('dashboard', absolute: false));
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
