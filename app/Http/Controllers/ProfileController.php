<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Support\AdminWeatherNoticeNotification;
use App\Support\BehaviorRecommendationNotification;
use App\Support\MitraApplicationStatusNotification;
use App\Support\PaymentOrderStatusNotification;
use App\Services\Location\LocationResolver;
use App\Services\WalletService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService
    ) {
    }

    private const NOTIFICATION_FILTER_ALL = 'all';

    private const NOTIFICATION_FILTER_UNREAD = 'unread';

    private const NOTIFICATION_FILTER_READ = 'read';

    private const NOTIFICATION_TYPE_ALL = 'all';

    private const NOTIFICATION_TYPE_PAYMENT = 'payment';

    private const NOTIFICATION_TYPE_WEATHER = 'weather';

    private const NOTIFICATION_TYPE_RECOMMENDATION = 'recommendation';

    private const NOTIFICATION_TYPE_MITRA_APPLICATION = 'mitra_application';

    private const NOTIFICATION_TYPE_SYSTEM = 'system';

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();
        $consumerProfile = null;
        $resolvedLocation = app(LocationResolver::class)->forUser($user);
        $hasLocationSet = (int) ($user?->province_id ?? 0) > 0 && (int) ($user?->city_id ?? 0) > 0;
        $walletBalance = Schema::hasTable('wallet_transactions')
            ? $this->walletService->getBalance((int) $user->id)
            : 0.0;

        if ($user?->isConsumer() && Schema::hasTable('consumer_profiles')) {
            $consumerProfile = DB::table('consumer_profiles')
                ->where('user_id', $user->id)
                ->first();
        }

        return view('profile.edit', [
            'user' => $user,
            'consumerProfile' => $consumerProfile,
            'profileLocationLabel' => (string) ($resolvedLocation['label'] ?? 'Belum diset'),
            'profileHasLocationSet' => $hasLocationSet,
            'walletBalance' => $walletBalance,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $oldAvatarPath = trim((string) ($user?->avatar_path ?? ''));
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

        return Redirect::route('profile.edit')->with('status', 'Foto profil berhasil diperbarui.');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();
        $oldAvatarPath = trim((string) ($user?->avatar_path ?? ''));

        if ($oldAvatarPath !== '' && Storage::disk('public')->exists($oldAvatarPath)) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        $user->forceFill([
            'avatar_path' => null,
        ])->save();

        return Redirect::route('profile.edit')->with('status', 'Foto profil dihapus.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    public function notifications(Request $request): View
    {
        $user = $request->user();
        $statusFilter = $this->normalizeNotificationFilter($request->string('status')->toString());
        $typeFilter = $this->normalizeNotificationTypeFilter($request->string('type')->toString());

        $notifications = new LengthAwarePaginator([], 0, 12);
        $notificationCounts = [
            self::NOTIFICATION_FILTER_ALL => 0,
            self::NOTIFICATION_FILTER_UNREAD => 0,
            self::NOTIFICATION_FILTER_READ => 0,
        ];
        $notificationTypeCounts = [
            self::NOTIFICATION_TYPE_ALL => 0,
            self::NOTIFICATION_TYPE_PAYMENT => 0,
            self::NOTIFICATION_TYPE_WEATHER => 0,
            self::NOTIFICATION_TYPE_RECOMMENDATION => 0,
            self::NOTIFICATION_TYPE_MITRA_APPLICATION => 0,
            self::NOTIFICATION_TYPE_SYSTEM => 0,
        ];

        if ($user && Schema::hasTable('notifications')) {
            $notificationsQuery = $user->notifications()->latest();
            $this->applyNotificationTypeFilter($notificationsQuery, $typeFilter);
            if ($statusFilter === self::NOTIFICATION_FILTER_UNREAD) {
                $notificationsQuery->whereNull('read_at');
            } elseif ($statusFilter === self::NOTIFICATION_FILTER_READ) {
                $notificationsQuery->whereNotNull('read_at');
            }

            $notifications = $notificationsQuery->paginate(12)->withQueryString();
            $countQuery = $user->notifications();
            $this->applyNotificationTypeFilter($countQuery, $typeFilter);
            $notificationCounts = [
                self::NOTIFICATION_FILTER_ALL => (int) (clone $countQuery)->count(),
                self::NOTIFICATION_FILTER_UNREAD => (int) (clone $countQuery)->whereNull('read_at')->count(),
                self::NOTIFICATION_FILTER_READ => (int) (clone $countQuery)->whereNotNull('read_at')->count(),
            ];
            $notificationTypeCounts = [
                self::NOTIFICATION_TYPE_ALL => (int) $user->notifications()->count(),
                self::NOTIFICATION_TYPE_PAYMENT => (int) $user->notifications()->where('type', PaymentOrderStatusNotification::class)->count(),
                self::NOTIFICATION_TYPE_WEATHER => (int) $user->notifications()->where('type', AdminWeatherNoticeNotification::class)->count(),
                self::NOTIFICATION_TYPE_RECOMMENDATION => (int) $user->notifications()->where('type', BehaviorRecommendationNotification::class)->count(),
                self::NOTIFICATION_TYPE_MITRA_APPLICATION => (int) $user->notifications()->where('type', MitraApplicationStatusNotification::class)->count(),
                self::NOTIFICATION_TYPE_SYSTEM => (int) $user->notifications()->whereNotIn('type', [
                    PaymentOrderStatusNotification::class,
                    AdminWeatherNoticeNotification::class,
                    BehaviorRecommendationNotification::class,
                    MitraApplicationStatusNotification::class,
                ])->count(),
            ];
        }

        return view('profile.notifications', [
            'notifications' => $notifications,
            'notificationCounts' => $notificationCounts,
            'statusFilter' => $statusFilter,
            'notificationTypeCounts' => $notificationTypeCounts,
            'typeFilter' => $typeFilter,
            'notificationCount' => (int) ($notificationCounts[self::NOTIFICATION_FILTER_UNREAD] ?? 0),
        ]);
    }

    public function markMitraNotificationsRead(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! Schema::hasTable('notifications')) {
            return Redirect::route('profile.edit');
        }

        $user->unreadNotifications()
            ->where('type', MitraApplicationStatusNotification::class)
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return Redirect::to(route('program.mitra.form') . '#mitra-notifications')
            ->with('status', 'Notifikasi pengajuan mitra sudah ditandai sebagai dibaca.');
    }

    public function markAllNotificationsRead(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! Schema::hasTable('notifications')) {
            return Redirect::route('notifications.index');
        }

        $typeFilter = $this->normalizeNotificationTypeFilter($request->string('type')->toString());
        $unreadNotificationsQuery = $user->unreadNotifications();
        $this->applyNotificationTypeFilter($unreadNotificationsQuery, $typeFilter);
        $unreadNotificationsQuery->update([
            'read_at' => now(),
            'updated_at' => now(),
        ]);

        return Redirect::to(route('notifications.index', $this->buildNotificationRedirectFilters($request)))
            ->with('status', 'Semua notifikasi berhasil ditandai sebagai dibaca.');
    }

    public function markNotificationRead(Request $request, string $notificationId): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! Schema::hasTable('notifications')) {
            return Redirect::route('notifications.index');
        }

        $notification = $user->notifications()
            ->where('id', $notificationId)
            ->first();

        if (! $notification) {
            return Redirect::to(route('notifications.index', $this->buildNotificationRedirectFilters($request)))
                ->with('error', 'Notifikasi tidak ditemukan.');
        }

        if (is_null($notification->read_at)) {
            $notification->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $actionRedirect = $this->resolveNotificationActionRedirect($request);
        if ($actionRedirect !== null) {
            return Redirect::to($actionRedirect);
        }

        return Redirect::to(route('notifications.index', $this->buildNotificationRedirectFilters($request)))
            ->with('status', 'Notifikasi sudah ditandai sebagai dibaca.');
    }

    private function normalizeNotificationFilter(string $value): string
    {
        return in_array($value, [
            self::NOTIFICATION_FILTER_ALL,
            self::NOTIFICATION_FILTER_UNREAD,
            self::NOTIFICATION_FILTER_READ,
        ], true) ? $value : self::NOTIFICATION_FILTER_ALL;
    }

    private function normalizeNotificationTypeFilter(string $value): string
    {
        return in_array($value, [
            self::NOTIFICATION_TYPE_ALL,
            self::NOTIFICATION_TYPE_PAYMENT,
            self::NOTIFICATION_TYPE_WEATHER,
            self::NOTIFICATION_TYPE_RECOMMENDATION,
            self::NOTIFICATION_TYPE_MITRA_APPLICATION,
            self::NOTIFICATION_TYPE_SYSTEM,
        ], true) ? $value : self::NOTIFICATION_TYPE_ALL;
    }

    private function applyNotificationTypeFilter($query, string $typeFilter): void
    {
        if ($typeFilter === self::NOTIFICATION_TYPE_PAYMENT) {
            $query->where('type', PaymentOrderStatusNotification::class);

            return;
        }

        if ($typeFilter === self::NOTIFICATION_TYPE_WEATHER) {
            $query->where('type', AdminWeatherNoticeNotification::class);

            return;
        }

        if ($typeFilter === self::NOTIFICATION_TYPE_RECOMMENDATION) {
            $query->where('type', BehaviorRecommendationNotification::class);

            return;
        }

        if ($typeFilter === self::NOTIFICATION_TYPE_MITRA_APPLICATION) {
            $query->where('type', MitraApplicationStatusNotification::class);

            return;
        }

        if ($typeFilter === self::NOTIFICATION_TYPE_SYSTEM) {
            $query->whereNotIn('type', [
                PaymentOrderStatusNotification::class,
                AdminWeatherNoticeNotification::class,
                BehaviorRecommendationNotification::class,
                MitraApplicationStatusNotification::class,
            ]);
        }
    }

    private function buildNotificationRedirectFilters(Request $request): array
    {
        $filters = [];

        if ($request->has('status')) {
            $filters['status'] = $this->normalizeNotificationFilter($request->string('status')->toString());
        }

        if ($request->has('type')) {
            $filters['type'] = $this->normalizeNotificationTypeFilter($request->string('type')->toString());
        }

        return $filters;
    }

    private function resolveNotificationActionRedirect(Request $request): ?string
    {
        $redirectTarget = trim((string) $request->input('redirect_to', ''));
        if ($redirectTarget === '') {
            return null;
        }

        if (str_starts_with($redirectTarget, '/') && ! str_starts_with($redirectTarget, '//')) {
            return $redirectTarget;
        }

        $parsedTarget = parse_url($redirectTarget);
        if (! is_array($parsedTarget)) {
            return null;
        }

        $targetHost = strtolower((string) ($parsedTarget['host'] ?? ''));
        $targetScheme = strtolower((string) ($parsedTarget['scheme'] ?? ''));
        $appHost = strtolower((string) parse_url((string) config('app.url', ''), PHP_URL_HOST));
        $appScheme = strtolower((string) parse_url((string) config('app.url', ''), PHP_URL_SCHEME));

        if ($targetHost === '' || $appHost === '' || $targetHost !== $appHost) {
            return null;
        }

        if ($targetScheme !== '' && $appScheme !== '' && $targetScheme !== $appScheme) {
            return null;
        }

        $path = (string) ($parsedTarget['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        } elseif (! str_starts_with($path, '/')) {
            $path = '/' . ltrim($path, '/');
        }

        $query = trim((string) ($parsedTarget['query'] ?? ''));

        return $query !== '' ? "{$path}?{$query}" : $path;
    }
}
