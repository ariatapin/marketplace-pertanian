<?php

namespace App\View\Components;

use App\Support\AdminWeatherNoticeNotification;
use App\Support\BehaviorRecommendationNotification;
use App\Models\User;
use App\Services\Location\LocationResolver;
use App\Services\Weather\WeatherAlertEngine;
use App\Services\Weather\WeatherService;
use App\Support\WeatherWidgetViewModelFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Component;
use Illuminate\View\View;

class WeatherWidget extends Component
{
    public bool $compact;
    public bool $minimal;

    /** @var array<int, array<string, mixed>> */
    public array $notifications;

    public int $unreadCount;

    public string $markReadRedirect;

    /**
     * @param iterable<int, mixed>|array<int, mixed> $notifications
     */
    public function __construct(
        bool $compact = false,
        bool $minimal = false,
        iterable $notifications = [],
        int $unreadCount = 0,
        ?string $markReadRedirect = null
    ) {
        $this->compact = $compact;
        $this->minimal = $minimal;
        $this->notifications = collect($notifications)
            ->map(function ($notification) {
                if (is_array($notification)) {
                    return $notification;
                }

                if (is_object($notification)) {
                    return (array) $notification;
                }

                return [];
            })
            ->values()
            ->all();
        $this->unreadCount = max(0, $unreadCount);
        $this->markReadRedirect = trim((string) $markReadRedirect);
    }

    public function render(): View
    {
        /** @var User|null $viewer */
        $viewer = auth()->user();

        $locationResolver = app(LocationResolver::class);
        $loc = $locationResolver->forUser($viewer);
        if (! is_array($loc) || $loc === []) {
            $loc = $locationResolver->fallback();
        }

        $locationType = (string) ($loc['type'] ?? 'custom');
        $locationId = (int) ($loc['id'] ?? 0);
        $locationLat = (float) ($loc['lat'] ?? 0);
        $locationLng = (float) ($loc['lng'] ?? 0);

        $current = [];
        $forecast = [];

        try {
            $weatherService = app(WeatherService::class);
            $current = $weatherService->current(
                $locationType,
                $locationId,
                $locationLat,
                $locationLng
            );
            $forecast = $weatherService->forecast(
                $locationType,
                $locationId,
                $locationLat,
                $locationLng
            );
        } catch (\Throwable $e) {
            $current = [];
            $forecast = [];
        }

        $engine = app(WeatherAlertEngine::class);
        $alert = $engine->evaluateForecast(is_array($forecast) ? $forecast : []);

        $adminNotice = $this->resolveAdminNotice($viewer);

        $viewData = app(WeatherWidgetViewModelFactory::class)->make(
            loc: is_array($loc) ? $loc : [],
            current: is_array($current) ? $current : [],
            alert: is_array($alert) ? $alert : [],
            adminNotice: $adminNotice
        );

        [$fallbackNotifications, $fallbackUnreadCount] = $this->resolveWeatherNotifications($viewer);
        $notificationRows = collect($this->notifications);

        if ($notificationRows->isEmpty() && $fallbackNotifications->isNotEmpty()) {
            $notificationRows = $fallbackNotifications;
        }

        $notificationUnreadCount = $this->unreadCount > 0
            ? $this->unreadCount
            : (int) $notificationRows
                ->filter(fn ($row) => (bool) ($row['is_unread'] ?? false))
                ->count();

        if ($notificationUnreadCount === 0 && $notificationRows->isEmpty()) {
            $notificationUnreadCount = $fallbackUnreadCount;
        }

        return view('components.weather-widget', array_merge($viewData, [
            'compact' => $this->compact,
            'minimal' => $this->minimal,
            'notifications' => $notificationRows->values()->all(),
            'unreadCount' => $notificationUnreadCount,
            'markReadRedirect' => $this->markReadRedirect,
        ]));
    }

    private function resolveAdminNotice(?User $viewer): ?object
    {
        if (! Schema::hasTable('admin_weather_notices')) {
            return null;
        }

        $viewerProvinceId = (int) ($viewer->province_id ?? 0);
        $viewerCityId = (int) ($viewer->city_id ?? 0);

        $noticeQuery = DB::table('admin_weather_notices')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            });

        $noticeQuery->where(function ($query) use ($viewerProvinceId, $viewerCityId) {
            if ($viewerCityId > 0) {
                $query->orWhere('city_id', $viewerCityId);
            }

            if ($viewerProvinceId > 0) {
                $query->orWhere(function ($sub) use ($viewerProvinceId) {
                    $sub->whereNull('city_id')
                        ->where('province_id', $viewerProvinceId);
                });
            }

            $query->orWhere(function ($sub) {
                $sub->whereNull('province_id')
                    ->whereNull('city_id')
                    ->whereNull('district_id');
            });
        });

        return $noticeQuery
            ->orderByRaw("CASE
                WHEN city_id IS NOT NULL THEN 0
                WHEN province_id IS NOT NULL THEN 1
                ELSE 2
            END")
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * @return array{0: Collection<int, array<string, mixed>>, 1: int}
     */
    private function resolveWeatherNotifications(?User $viewer): array
    {
        if (! $viewer || ! Schema::hasTable('notifications')) {
            return [collect(), 0];
        }

        $query = $viewer->notifications()
            ->where(function ($innerQuery) {
                $innerQuery->whereIn('type', [
                    AdminWeatherNoticeNotification::class,
                    BehaviorRecommendationNotification::class,
                ])->orWhere('data', 'like', '%"category":"behavior_recommendation"%');
            })
            ->latest();

        $unreadCount = (int) (clone $query)->whereNull('read_at')->count();

        $rows = $query
            ->limit(4)
            ->get()
            ->map(fn ($notification) => $this->formatWeatherNotificationRow($notification))
            ->values();

        return [$rows, $unreadCount];
    }

    /**
     * @param  object  $notification
     * @return array<string, mixed>
     */
    private function formatWeatherNotificationRow(object $notification): array
    {
        $typeClass = (string) data_get($notification, 'type', '');
        $isRecommendation = $typeClass === BehaviorRecommendationNotification::class
            || (string) data_get($notification, 'data.category', '') === 'behavior_recommendation';
        $status = strtolower(trim((string) data_get($notification, 'data.status', 'unknown')));
        $badgeClass = match ($status) {
            'red' => 'border-rose-200 bg-rose-100 text-rose-700',
            'yellow' => 'border-amber-200 bg-amber-100 text-amber-700',
            'green' => 'border-emerald-200 bg-emerald-100 text-emerald-700',
            default => 'border-slate-200 bg-slate-100 text-slate-700',
        };

        $sentAtLabel = '-';
        $sentAtRaw = data_get($notification, 'data.sent_at') ?: data_get($notification, 'created_at');
        if ($sentAtRaw) {
            try {
                $sentAtLabel = Carbon::parse($sentAtRaw)->diffForHumans();
            } catch (\Throwable $e) {
                $sentAtLabel = '-';
            }
        }

        $validUntilLabel = null;
        $validUntilRaw = data_get($notification, 'data.valid_until');
        if ($validUntilRaw) {
            try {
                $validUntilLabel = Carbon::parse($validUntilRaw)->translatedFormat('d M Y H:i');
            } catch (\Throwable $e) {
                $validUntilLabel = null;
            }
        }

        return [
            'id' => (string) data_get($notification, 'id'),
            'type' => $isRecommendation ? 'recommendation' : 'weather',
            'type_label' => $isRecommendation ? 'Rekomendasi' : 'Cuaca',
            'filter_type' => $isRecommendation ? 'recommendation' : 'weather',
            'status' => $status,
            'badge_class' => $badgeClass,
            'title' => trim((string) data_get(
                $notification,
                'data.title',
                $isRecommendation ? 'Rekomendasi Operasional' : 'Notifikasi Cuaca'
            )),
            'message' => trim((string) data_get(
                $notification,
                'data.message',
                $isRecommendation
                    ? 'Ada rekomendasi operasional baru untuk akun Anda.'
                    : 'Ada pembaruan cuaca untuk wilayah Anda.'
            )),
            'target_label' => trim((string) data_get($notification, 'data.target_label', 'Wilayah Akun')),
            'sent_at_label' => $sentAtLabel,
            'valid_until_label' => $validUntilLabel,
            'is_unread' => is_null(data_get($notification, 'read_at')),
        ];
    }
}
