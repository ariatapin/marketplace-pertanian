<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Location\LocationResolver;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminProfileController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService
    ) {
    }

    public function show(Request $request): View
    {
        $user = $request->user();
        $adminProfile = null;
        $resolvedLocation = app(LocationResolver::class)->forUser($user);
        $hasLocationSet = (int) ($user?->province_id ?? 0) > 0 && (int) ($user?->city_id ?? 0) > 0;
        $walletBalance = Schema::hasTable('wallet_transactions')
            ? $this->walletService->getBalance((int) $user->id)
            : 0.0;

        if ($user && Schema::hasTable('admin_profiles')) {
            $adminProfile = DB::table('admin_profiles')
                ->where('user_id', $user->id)
                ->first();
        }

        return view('admin.profile', [
            'user' => $user,
            'adminProfile' => $adminProfile,
            'profileLocationLabel' => (string) ($resolvedLocation['label'] ?? 'Belum diset'),
            'profileHasLocationSet' => $hasLocationSet,
            'walletBalance' => $walletBalance,
        ]);
    }

    public function updateAccount(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return Redirect::route('admin.profile');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
            'phone_number' => ['nullable', 'string', 'max:30'],
        ]);

        $normalizedEmail = strtolower(trim((string) $validated['email']));
        $user->name = trim((string) $validated['name']);
        $user->email = $normalizedEmail;
        $user->phone_number = trim((string) ($validated['phone_number'] ?? '')) ?: null;

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('admin.profile')->with('status', 'admin-profile-updated');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return Redirect::route('admin.profile');
        }

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $oldAvatarPath = trim((string) ($user->avatar_path ?? ''));
        $newAvatarPath = (string) $request->file('avatar')->store('avatars/users/' . $user->id, 'public');

        $user->forceFill([
            'avatar_path' => $newAvatarPath,
        ])->save();

        if (
            $oldAvatarPath !== ''
            && $oldAvatarPath !== $newAvatarPath
            && ! str_starts_with($oldAvatarPath, 'http://')
            && ! str_starts_with($oldAvatarPath, 'https://')
            && Storage::disk('public')->exists($oldAvatarPath)
        ) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        return Redirect::route('admin.profile')->with('status', 'admin-avatar-updated');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return Redirect::route('admin.profile');
        }

        $oldAvatarPath = trim((string) ($user->avatar_path ?? ''));

        if ($oldAvatarPath !== '' && Storage::disk('public')->exists($oldAvatarPath)) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        $user->forceFill([
            'avatar_path' => null,
        ])->save();

        return Redirect::route('admin.profile')->with('status', 'admin-avatar-removed');
    }

    public function updateOps(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return Redirect::route('admin.profile');
        }

        if (! Schema::hasTable('admin_profiles')) {
            return Redirect::route('admin.profile')->withErrors([
                'admin_profile' => 'Tabel admin_profiles belum tersedia. Jalankan migrasi terlebih dahulu.',
            ]);
        }

        $validated = $request->validate([
            'platform_name' => ['required', 'string', 'max:120'],
            'support_email' => ['nullable', 'email:rfc', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:50'],
            'default_courier' => ['nullable', 'string', 'max:100'],
        ]);

        $payload = [
            'platform_name' => trim((string) $validated['platform_name']),
            'support_email' => trim((string) ($validated['support_email'] ?? '')) ?: null,
            'support_phone' => trim((string) ($validated['support_phone'] ?? '')) ?: null,
            'default_courier' => trim((string) ($validated['default_courier'] ?? '')) ?: null,
            'updated_at' => now(),
        ];

        $profileExists = DB::table('admin_profiles')
            ->where('user_id', $user->id)
            ->exists();

        if (! $profileExists) {
            $payload['created_at'] = now();
        }

        DB::table('admin_profiles')->updateOrInsert(
            ['user_id' => $user->id],
            $payload
        );

        return Redirect::route('admin.profile')->with('status', 'admin-ops-updated');
    }
}
