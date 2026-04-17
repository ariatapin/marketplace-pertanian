<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Location\LocationResolver;
use App\Services\Weather\WeatherAlertEngine;
use App\Services\Weather\WeatherService;
use App\Support\AdminWeatherViewModelFactory;
use App\Support\AdminWeatherNoticeNotification;
use App\Support\BehaviorRecommendationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WeatherPageController extends Controller
{
    public function __construct(
        protected AdminWeatherViewModelFactory $weatherViewModelFactory
    ) {}

    public function __invoke(Request $request)
    {
        // CATATAN-AUDIT: Satu endpoint multi-panel (status cuaca, notifikasi manual, dan otomatisasi notifikasi dari rule recommendation).
        $panel = trim($request->string('panel')->toString());
        $noticeFilterRequested = $request->filled('notice_status')
            || $request->filled('notice_scope')
            || $request->filled('notice_severity')
            || $request->filled('notice_validity')
            || $request->filled('notice_province_id')
            || $request->filled('notice_city_id')
            || $request->filled('notice_q');
        if (! in_array($panel, ['status', 'notice', 'automation'], true)) {
            $panel = $noticeFilterRequested ? 'notice' : 'status';
        }

        $statusProvinceId = trim($request->string('status_province_id')->toString());
        $statusCityId = trim($request->string('status_city_id')->toString());
        $statusQuery = trim($request->string('status_q')->toString());
        $statusQueryNormalized = $this->normalizeLocationKeyword($statusQuery);
        $statusQueryTokens = $this->locationKeywordTokens($statusQueryNormalized);
        $commodity = trim($request->string('commodity')->toString());
        $noticeStatus = trim($request->string('notice_status')->toString());
        $noticeScope = trim($request->string('notice_scope')->toString());
        $noticeSeverity = trim($request->string('notice_severity')->toString());
        $noticeValidity = trim($request->string('notice_validity')->toString());
        $noticeProvinceId = trim($request->string('notice_province_id')->toString());
        $noticeCityId = trim($request->string('notice_city_id')->toString());
        $noticeKeyword = trim($request->string('notice_q')->toString());
        $noticeKeywordNormalized = $this->normalizeLocationKeyword($noticeKeyword);
        $noticeKeywordTokens = $this->locationKeywordTokens($noticeKeywordNormalized);
        $noticeKeywordSeverities = $this->inferWeatherSeveritiesByKeyword($noticeKeywordNormalized, $noticeKeywordTokens);
        $automationKeyword = trim($request->string('automation_q')->toString());
        $automationRoleTarget = strtolower(trim($request->string('automation_role_target')->toString()));
        $automationReadStatus = strtolower(trim($request->string('automation_read_status')->toString()));
        $automationRuleKey = trim($request->string('automation_rule_key')->toString());

        $summary = [
            'green' => 0,
            'yellow' => 0,
            'red' => 0,
            'unknown' => 0,
        ];

        $weatherRows = collect();
        $latestSnapshots = collect();
        $apiWarnings = collect();
        $commodities = collect();
        $provinces = collect();
        $adminWeatherNotices = collect();
        $recommendationNotifications = collect();
        $recommendationRuleKeys = collect();
        $noticeCityTargets = collect();
        $noticeWeatherMatches = collect();
        $noticeRecipientStats = [
            'global_total' => 0,
            'province_totals' => [],
            'city_totals' => [],
        ];
        $recommendationSummary = [
            'total' => 0,
            'unread' => 0,
            'read' => 0,
            'consumer' => 0,
            'mitra' => 0,
            'today' => 0,
            'filtered' => 0,
        ];
        $statusTargetLabel = 'Semua Wilayah';

        $canFetchFromApi = (string) config('weather.provider', 'openweather') === 'openweather'
            && filled(config('weather.openweather.key'))
            && ! app()->environment('testing');
        $alertEngine = app(WeatherAlertEngine::class);
        $weatherService = $canFetchFromApi ? app(WeatherService::class) : null;

        $cityRows = collect();
        if (
            Schema::hasTable('users')
            && Schema::hasTable('cities')
            && Schema::hasTable('provinces')
            && Schema::hasColumn('users', 'city_id')
        ) {
            $cityQuery = DB::table('cities')
                ->join('provinces', 'provinces.id', '=', 'cities.province_id')
                ->leftJoin('users', 'users.city_id', '=', 'cities.id')
                ->select(
                    'cities.id as city_id',
                    'cities.name as city_name',
                    'cities.type as city_type',
                    'cities.lat',
                    'cities.lng',
                    'provinces.id as province_id',
                    'provinces.name as province_name',
                    DB::raw('COUNT(users.id) as total_users')
                )
                ->groupBy('cities.id', 'cities.name', 'cities.type', 'cities.lat', 'cities.lng', 'provinces.id', 'provinces.name')
                ->orderByDesc('total_users')
                ->orderBy('cities.name');

            if ($statusProvinceId !== '' && ctype_digit($statusProvinceId)) {
                $cityQuery->where('provinces.id', (int) $statusProvinceId);
            }

            if ($statusCityId !== '' && ctype_digit($statusCityId)) {
                $cityQuery->where('cities.id', (int) $statusCityId);
            }

            if ($statusQueryNormalized !== '') {
                $cityQuery->where(function ($outerQuery) use ($statusQueryNormalized, $statusQueryTokens) {
                    $this->applyLocationKeywordWhere($outerQuery, $statusQueryNormalized);

                    if (count($statusQueryTokens) > 1) {
                        $outerQuery->orWhere(function ($allTokensQuery) use ($statusQueryTokens) {
                            foreach ($statusQueryTokens as $token) {
                                $allTokensQuery->where(function ($tokenQuery) use ($token) {
                                    $this->applyLocationKeywordWhere($tokenQuery, $token);
                                });
                            }
                        });
                    }
                });
            }

            if ($statusQueryNormalized !== '') {
                $cityQuery->limit(200);
            } elseif ($statusCityId === '' && $statusProvinceId === '') {
                $cityQuery->limit(40);
            } elseif ($statusCityId === '') {
                $cityQuery->limit(120);
            } else {
                $cityQuery->limit(1);
            }

            $cityRows = $cityQuery->get();

            $provinces = DB::table('provinces')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        }

        if (Schema::hasTable('farmer_harvests')) {
            $commodities = DB::table('farmer_harvests')
                ->select('name')
                ->distinct()
                ->orderBy('name')
                ->limit(40)
                ->pluck('name');
        }

        if ($cityRows->isEmpty()) {
            $fallback = app(LocationResolver::class)->fallback();
            $cityRows = collect([
                (object) [
                    'city_id' => (int) $fallback['id'],
                    'city_name' => $fallback['label'],
                    'city_type' => '',
                    'province_id' => null,
                    'province_name' => 'Unknown',
                    'lat' => $fallback['lat'],
                    'lng' => $fallback['lng'],
                    'total_users' => 0,
                ],
            ]);
        }

        if (Schema::hasTable('cities') && Schema::hasTable('provinces')) {
            $noticeCityTargets = DB::table('cities')
                ->join('provinces', 'provinces.id', '=', 'cities.province_id')
                ->select(
                    'cities.id as city_id',
                    'cities.name as city_name',
                    'cities.type as city_type',
                    'provinces.id as province_id',
                    'provinces.name as province_name'
                )
                ->orderBy('provinces.name')
                ->orderBy('cities.name')
                ->get()
                ->map(function ($city) {
                    $label = trim(($city->city_type ? $city->city_type . ' ' : '') . $city->city_name);

                    return (object) [
                        'city_id' => (int) $city->city_id,
                        'province_id' => (int) $city->province_id,
                        'label' => $label !== '' ? $label : 'Kota',
                        'province_name' => $city->province_name ?? '-',
                    ];
                })
                ->values();
        }

        if ($noticeCityTargets->isEmpty()) {
            $noticeCityTargets = $cityRows
                ->map(function ($city) {
                    $label = trim(($city->city_type ? $city->city_type . ' ' : '') . $city->city_name);

                    return (object) [
                        'city_id' => (int) $city->city_id,
                        'province_id' => isset($city->province_id) ? (int) $city->province_id : null,
                        'label' => $label !== '' ? $label : 'Kota',
                        'province_name' => $city->province_name ?? '-',
                    ];
                })
                ->unique('city_id')
                ->values();
        }

        if (Schema::hasTable('users')) {
            $baseRecipientQuery = DB::table('users')
                ->whereRaw('LOWER(TRIM(role)) <> ?', [User::normalizeRoleValue('admin')]);

            $noticeRecipientStats['global_total'] = (int) (clone $baseRecipientQuery)->count();

            if (Schema::hasColumn('users', 'province_id')) {
                $noticeRecipientStats['province_totals'] = (clone $baseRecipientQuery)
                    ->whereNotNull('province_id')
                    ->select('province_id', DB::raw('COUNT(*) as total'))
                    ->groupBy('province_id')
                    ->pluck('total', 'province_id')
                    ->map(fn ($total) => (int) $total)
                    ->toArray();
            }

            if (Schema::hasColumn('users', 'city_id')) {
                $noticeRecipientStats['city_totals'] = (clone $baseRecipientQuery)
                    ->whereNotNull('city_id')
                    ->select('city_id', DB::raw('COUNT(*) as total'))
                    ->groupBy('city_id')
                    ->pluck('total', 'city_id')
                    ->map(fn ($total) => (int) $total)
                    ->toArray();
            }
        }

        if ($statusCityId !== '' && ctype_digit($statusCityId) && Schema::hasTable('cities')) {
            $selectedCity = DB::table('cities')
                ->leftJoin('provinces', 'provinces.id', '=', 'cities.province_id')
                ->where('cities.id', (int) $statusCityId)
                ->first([
                    'cities.name as city_name',
                    'cities.type as city_type',
                    'provinces.name as province_name',
                ]);

            if ($selectedCity) {
                $statusTargetLabel = trim(((string) ($selectedCity->city_type ?? '')) . ' ' . ((string) ($selectedCity->city_name ?? '')));
                $provinceName = trim((string) ($selectedCity->province_name ?? ''));
                if ($provinceName !== '') {
                    $statusTargetLabel .= ', ' . $provinceName;
                }
            }
        } elseif ($statusProvinceId !== '' && ctype_digit($statusProvinceId) && Schema::hasTable('provinces')) {
            $provinceName = DB::table('provinces')
                ->where('id', (int) $statusProvinceId)
                ->value('name');

            if (filled($provinceName)) {
                $statusTargetLabel = 'Provinsi ' . (string) $provinceName;
            }
        }

        if ($statusTargetLabel === 'Semua Wilayah' && $statusQuery !== '') {
            $statusTargetLabel = 'Hasil pencarian: ' . $statusQuery;
        }

        $bmkgRegionCodeMap = [];
        if (Schema::hasTable('districts')) {
            $bmkgRegionCodeMap = DB::table('districts')
                ->selectRaw('city_id, MIN(id) as district_id')
                ->groupBy('city_id')
                ->pluck('district_id', 'city_id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        }

        foreach ($cityRows as $city) {
            $lat = (float) ($city->lat ?? 0);
            $lng = (float) ($city->lng ?? 0);
            $hasCoordinates = ! ($lat === 0.0 && $lng === 0.0);
            $requestLat = $hasCoordinates ? $lat : (float) config('weather.fallback.lat', 0);
            $requestLng = $hasCoordinates ? $lng : (float) config('weather.fallback.lng', 0);

            $cityId = (int) $city->city_id;
            $hasDistrictBmkgCode = array_key_exists($cityId, $bmkgRegionCodeMap);
            $bmkgCode = $this->resolveBmkgCodeForCity($cityId, $bmkgRegionCodeMap);
            $currentSnapshot = $this->latestWeatherSnapshotForCity('current', $cityId);
            $forecastSnapshot = $this->latestWeatherSnapshotForCity('forecast', $cityId);
            $currentPayload = [];
            $forecastPayload = [];
            $sourceLabel = $hasCoordinates ? 'Snapshot OpenWeather' : 'Koordinat belum tersedia';
            $isLiveFetch = false;

            if ($canFetchFromApi && $weatherService) {
                try {
                    $currentPayload = $weatherService->current('city', $cityId, $requestLat, $requestLng);
                    $forecastPayload = $weatherService->forecast('city', $cityId, $requestLat, $requestLng);
                    if (! $currentSnapshot || $this->isWeatherSnapshotStale($currentSnapshot->valid_until ?? null)) {
                        $currentSnapshot = $this->latestWeatherSnapshotForCity('current', $cityId);
                    }
                    if (! $forecastSnapshot || $this->isWeatherSnapshotStale($forecastSnapshot->valid_until ?? null)) {
                        $forecastSnapshot = $this->latestWeatherSnapshotForCity('forecast', $cityId);
                    }
                    $isLiveFetch = true;
                } catch (\Throwable $e) {
                    $apiWarnings->push('Gagal fetch cuaca untuk ' . $city->city_name . ': ' . $e->getMessage());
                }
            }

            if (! is_array($currentPayload) || $currentPayload === []) {
                $currentPayload = $this->normalizeWeatherPayload($currentSnapshot?->payload ?? null);
            }
            if (! is_array($forecastPayload) || $forecastPayload === []) {
                $forecastPayload = $this->normalizeWeatherPayload($forecastSnapshot?->payload ?? null);
            }

            $current = $this->normalizeWeatherPayload($currentPayload);
            $forecast = $this->normalizeWeatherPayload($forecastPayload);
            $hasWeatherPayload = ! empty($current) || ! empty($forecast);
            $sourceCode = $this->resolveWeatherSourceCode($current, $forecast);
            if (! $isLiveFetch && $hasWeatherPayload) {
                $sourceLabel = $this->weatherSourceLabel($sourceCode, true);
            } elseif (! $hasWeatherPayload) {
                $sourceLabel = $hasCoordinates
                    ? 'Belum ada data cuaca'
                    : 'Koordinat belum tersedia (BMKG belum tersedia)';
            } else {
                $sourceLabel = $this->weatherSourceLabel($sourceCode, false);
            }
            $sourceReason = $this->resolveWeatherSourceReason(
                hasCoordinates: $hasCoordinates,
                hasWeatherPayload: $hasWeatherPayload,
                sourceCode: $sourceCode,
                isLiveFetch: $isLiveFetch,
                bmkgCode: $bmkgCode
            );

            $alert = $alertEngine->evaluateForecast($forecast);
            $severity = $alert['severity'] ?? 'unknown';
            $fetchedAt = $currentSnapshot?->fetched_at ?? $forecastSnapshot?->fetched_at;
            $snapshotValidUntil = $currentSnapshot?->valid_until ?? $forecastSnapshot?->valid_until;
            $isStale = $this->isWeatherSnapshotStale($snapshotValidUntil);

            if (isset($summary[$severity])) {
                $summary[$severity]++;
            } else {
                $summary['unknown']++;
            }

            $weatherRows->push([
                'label' => trim(($city->city_type ? $city->city_type . ' ' : '') . $city->city_name),
                'province_name' => $city->province_name ?? '-',
                'total_users' => (int) ($city->total_users ?? 0),
                'severity' => $severity,
                'message' => $alert['message'] ?? 'Data cuaca belum tersedia.',
                'temp' => data_get($current, 'main.temp'),
                'rain' => data_get($current, 'rain.1h', 0),
                'wind' => data_get($current, 'wind.speed'),
                'valid_until' => $alert['valid_until'] ?? null,
                'fetched_at' => $fetchedAt,
                'snapshot_valid_until' => $snapshotValidUntil,
                'source_label' => $sourceLabel,
                'bmkg_code_label' => $bmkgCode
                    ? ($hasDistrictBmkgCode ? $bmkgCode . ' (kecamatan)' : $bmkgCode . ' (fallback kota)')
                    : '-',
                'source_reason' => $sourceReason,
                'is_stale' => $isStale,
            ]);
        }

        if ($noticeKeywordNormalized !== '') {
            $noticeWeatherMatches = $weatherRows
                ->filter(function (array $row) use ($noticeKeywordNormalized, $noticeKeywordTokens, $noticeKeywordSeverities): bool {
                    $severity = strtolower((string) ($row['severity'] ?? 'unknown'));
                    $composed = Str::lower(trim(
                        ((string) ($row['label'] ?? '')) . ' '
                        . ((string) ($row['province_name'] ?? '')) . ' '
                        . ((string) ($row['message'] ?? '')) . ' '
                        . $severity
                    ));

                    $phraseMatch = $noticeKeywordNormalized !== '' && str_contains($composed, $noticeKeywordNormalized);
                    $tokenMatch = ! empty($noticeKeywordTokens) && collect($noticeKeywordTokens)->every(function (string $token) use ($composed, $severity): bool {
                        if ($token === '') {
                            return true;
                        }

                        $tokenSeverities = $this->inferWeatherSeveritiesByKeyword($token, [$token]);
                        if (! empty($tokenSeverities) && in_array($severity, $tokenSeverities, true)) {
                            return true;
                        }

                        return str_contains($composed, $token);
                    });
                    $severityMatch = ! empty($noticeKeywordSeverities) && in_array($severity, $noticeKeywordSeverities, true);

                    return $phraseMatch || $tokenMatch || $severityMatch;
                })
                ->sortByDesc(function (array $row): int {
                    $severity = strtolower((string) ($row['severity'] ?? 'unknown'));

                    return ($this->weatherSeverityRank($severity) * 1000000) + (int) ($row['total_users'] ?? 0);
                })
                ->take(20)
                ->values();
        }

        if (Schema::hasTable('weather_snapshots')) {
            $latestSnapshots = DB::table('weather_snapshots')
                ->where('provider', 'openweather')
                ->select('kind', 'location_type', 'location_id', 'fetched_at', 'valid_until')
                ->orderByDesc('fetched_at')
                ->limit(10)
                ->get();
        }

        if (Schema::hasTable('admin_weather_notices')) {
            $noticeQuery = DB::table('admin_weather_notices')
                ->leftJoin('provinces', 'provinces.id', '=', 'admin_weather_notices.province_id')
                ->leftJoin('cities', 'cities.id', '=', 'admin_weather_notices.city_id')
                ->leftJoin('users as creators', 'creators.id', '=', 'admin_weather_notices.created_by');

            if (in_array($noticeScope, ['global', 'province', 'city'], true)) {
                $noticeQuery->where('admin_weather_notices.scope', $noticeScope);
            }

            if (in_array($noticeSeverity, ['green', 'yellow', 'red', 'unknown'], true)) {
                $noticeQuery->where('admin_weather_notices.severity', $noticeSeverity);
            }

            if ($noticeProvinceId !== '' && ctype_digit($noticeProvinceId)) {
                $noticeQuery->where('admin_weather_notices.province_id', (int) $noticeProvinceId);
            }

            if ($noticeCityId !== '' && ctype_digit($noticeCityId)) {
                $noticeQuery->where('admin_weather_notices.city_id', (int) $noticeCityId);
            }

            if ($noticeStatus === 'active') {
                $noticeQuery
                    ->where('admin_weather_notices.is_active', true)
                    ->where(function ($query) {
                        $query->whereNull('admin_weather_notices.valid_until')
                            ->orWhere('admin_weather_notices.valid_until', '>=', now());
                    });
            } elseif ($noticeStatus === 'inactive') {
                $noticeQuery->where('admin_weather_notices.is_active', false);
            } elseif ($noticeStatus === 'expired') {
                $noticeQuery->whereNotNull('admin_weather_notices.valid_until')
                    ->where('admin_weather_notices.valid_until', '<', now());
            }

            if ($noticeValidity === 'expiring_24h') {
                $noticeQuery
                    ->whereNotNull('admin_weather_notices.valid_until')
                    ->whereBetween('admin_weather_notices.valid_until', [now(), now()->addHours(24)]);
            } elseif ($noticeValidity === 'expiring_72h') {
                $noticeQuery
                    ->whereNotNull('admin_weather_notices.valid_until')
                    ->whereBetween('admin_weather_notices.valid_until', [now(), now()->addHours(72)]);
            } elseif ($noticeValidity === 'no_expiry') {
                $noticeQuery->whereNull('admin_weather_notices.valid_until');
            }

            if ($noticeKeywordNormalized !== '') {
                $noticeQuery->where(function ($outerQuery) use ($noticeKeywordNormalized, $noticeKeywordTokens, $noticeKeywordSeverities) {
                    $this->applyNoticeKeywordWhere($outerQuery, $noticeKeywordNormalized, $noticeKeywordSeverities);

                    if (count($noticeKeywordTokens) > 1) {
                        $outerQuery->orWhere(function ($allTokensQuery) use ($noticeKeywordTokens) {
                            foreach ($noticeKeywordTokens as $token) {
                                $allTokensQuery->where(function ($tokenQuery) use ($token) {
                                    $tokenSeverities = $this->inferWeatherSeveritiesByKeyword($token, [$token]);
                                    $this->applyNoticeKeywordWhere($tokenQuery, $token, $tokenSeverities);
                                });
                            }
                        });
                    }
                });
            }

            $adminWeatherNotices = $noticeQuery
                ->orderByDesc('admin_weather_notices.created_at')
                ->limit(30)
                ->get([
                    'admin_weather_notices.id',
                    'admin_weather_notices.scope',
                    'admin_weather_notices.province_id',
                    'admin_weather_notices.city_id',
                    'admin_weather_notices.district_id',
                    'admin_weather_notices.severity',
                    'admin_weather_notices.title',
                    'admin_weather_notices.message',
                    'admin_weather_notices.valid_until',
                    'admin_weather_notices.is_active',
                    'admin_weather_notices.created_at',
                    'provinces.name as province_name',
                    'cities.name as city_name',
                    'cities.type as city_type',
                    'creators.name as created_by_name',
                ]);
        }

        if (Schema::hasTable('notifications') && Schema::hasTable('users')) {
            $recommendationSourceRows = DB::table('notifications')
                ->leftJoin('users as recipients', 'recipients.id', '=', 'notifications.notifiable_id')
                ->where('notifications.notifiable_type', User::class)
                ->where(function ($query) {
                    $query->where('notifications.type', BehaviorRecommendationNotification::class)
                        ->orWhere('notifications.data', 'like', '%"category":"behavior_recommendation"%');
                })
                ->orderByDesc('notifications.created_at')
                ->limit(600)
                ->get([
                    'notifications.id',
                    'notifications.type',
                    'notifications.notifiable_id',
                    'notifications.data',
                    'notifications.read_at',
                    'notifications.created_at',
                    'recipients.name as recipient_name',
                    'recipients.email as recipient_email',
                    'recipients.role as recipient_role',
                ]);

            $mappedRecommendationRows = $recommendationSourceRows->map(function ($row): array {
                $payload = json_decode((string) ($row->data ?? ''), true);
                if (! is_array($payload)) {
                    $payload = [];
                }

                $status = strtolower(trim((string) ($payload['status'] ?? 'unknown')));
                $roleTarget = strtolower(trim((string) ($payload['role_target'] ?? ($row->recipient_role ?? 'unknown'))));
                $ruleKey = trim((string) ($payload['rule_key'] ?? '-'));
                $title = trim((string) ($payload['title'] ?? 'Notifikasi Otomatis'));
                $message = trim((string) ($payload['message'] ?? '-'));
                $targetLabel = trim((string) ($payload['target_label'] ?? '-'));
                $dispatchKey = trim((string) ($payload['dispatch_key'] ?? ''));
                $actionLabel = trim((string) ($payload['action_label'] ?? ''));
                $actionUrl = trim((string) ($payload['action_url'] ?? ''));
                $sentAtRaw = $payload['sent_at'] ?? $row->created_at;
                $validUntilRaw = $payload['valid_until'] ?? null;
                $readAtRaw = $row->read_at ?? null;

                $sentAt = null;
                if (! empty($sentAtRaw)) {
                    try {
                        $sentAt = Carbon::parse((string) $sentAtRaw);
                    } catch (\Throwable $e) {
                        $sentAt = null;
                    }
                }

                $validUntil = null;
                if (! empty($validUntilRaw)) {
                    try {
                        $validUntil = Carbon::parse((string) $validUntilRaw);
                    } catch (\Throwable $e) {
                        $validUntil = null;
                    }
                }

                $readAt = null;
                if (! empty($readAtRaw)) {
                    try {
                        $readAt = Carbon::parse((string) $readAtRaw);
                    } catch (\Throwable $e) {
                        $readAt = null;
                    }
                }

                return [
                    'id' => (string) ($row->id ?? ''),
                    'status' => $status !== '' ? $status : 'unknown',
                    'role_target' => $roleTarget !== '' ? $roleTarget : 'unknown',
                    'rule_key' => $ruleKey !== '' ? $ruleKey : '-',
                    'title' => $title !== '' ? $title : 'Notifikasi Otomatis',
                    'message' => $message !== '' ? $message : '-',
                    'target_label' => $targetLabel !== '' ? $targetLabel : '-',
                    'dispatch_key' => $dispatchKey,
                    'action_label' => $actionLabel,
                    'action_url' => $actionUrl,
                    'recipient_name' => (string) ($row->recipient_name ?? '-'),
                    'recipient_email' => (string) ($row->recipient_email ?? '-'),
                    'recipient_role' => strtolower(trim((string) ($row->recipient_role ?? 'unknown'))),
                    'is_read' => $readAt !== null,
                    'read_at' => $readAt?->format('d M Y H:i') ?? '-',
                    'sent_at' => $sentAt?->format('d M Y H:i') ?? '-',
                    'sent_at_raw' => $sentAt,
                    'valid_until' => $validUntil?->format('d M Y H:i') ?? '-',
                    'valid_until_raw' => $validUntil,
                    'is_expired' => $validUntil?->isPast() ?? false,
                ];
            })->values();

            $recommendationRuleKeys = $mappedRecommendationRows
                ->pluck('rule_key')
                ->filter(fn ($ruleKey) => trim((string) $ruleKey) !== '' && (string) $ruleKey !== '-')
                ->unique()
                ->sort()
                ->values();

            $recommendationSummary = [
                'total' => (int) $mappedRecommendationRows->count(),
                'unread' => (int) $mappedRecommendationRows->where('is_read', false)->count(),
                'read' => (int) $mappedRecommendationRows->where('is_read', true)->count(),
                'consumer' => (int) $mappedRecommendationRows->where('role_target', 'consumer')->count(),
                'mitra' => (int) $mappedRecommendationRows->where('role_target', 'mitra')->count(),
                'seller' => (int) $mappedRecommendationRows->where('role_target', 'seller')->count(),
                'today' => (int) $mappedRecommendationRows->filter(function (array $row): bool {
                    $sentAt = $row['sent_at_raw'] ?? null;
                    if (! $sentAt instanceof Carbon) {
                        return false;
                    }

                    return $sentAt->isSameDay(now());
                })->count(),
                'filtered' => 0,
            ];

            $automationKeywordNormalized = $this->normalizeLocationKeyword($automationKeyword);
            $recommendationNotifications = $mappedRecommendationRows
                ->filter(function (array $row) use ($automationRoleTarget, $automationReadStatus, $automationRuleKey, $automationKeywordNormalized): bool {
                    if (in_array($automationRoleTarget, ['consumer', 'mitra', 'seller'], true) && (string) $row['role_target'] !== $automationRoleTarget) {
                        return false;
                    }

                    if ($automationReadStatus === 'unread' && (bool) $row['is_read']) {
                        return false;
                    }

                    if ($automationReadStatus === 'read' && ! (bool) $row['is_read']) {
                        return false;
                    }

                    if ($automationRuleKey !== '' && (string) $row['rule_key'] !== $automationRuleKey) {
                        return false;
                    }

                    if ($automationKeywordNormalized !== '') {
                        $haystack = $this->normalizeLocationKeyword(
                            trim(
                                (string) ($row['title'] ?? '') . ' '
                                . (string) ($row['message'] ?? '') . ' '
                                . (string) ($row['target_label'] ?? '') . ' '
                                . (string) ($row['recipient_name'] ?? '') . ' '
                                . (string) ($row['recipient_email'] ?? '')
                            )
                        );

                        if (! str_contains($haystack, $automationKeywordNormalized)) {
                            return false;
                        }
                    }

                    return true;
                })
                ->take(200)
                ->values();

            $recommendationSummary['filtered'] = (int) $recommendationNotifications->count();
        }

        $viewModel = $this->weatherViewModelFactory->make(
            summary: $summary,
            weatherRows: $weatherRows,
            latestSnapshots: $latestSnapshots,
            automationHints: collect(),
            adminWeatherNotices: $adminWeatherNotices
        );

        return view('admin.weather', array_merge(
            [
                'weatherRows' => $weatherRows,
                'latestSnapshots' => $latestSnapshots,
                'apiWarnings' => $apiWarnings,
                'canFetchFromApi' => $canFetchFromApi,
                'provinces' => $provinces,
                'commodities' => $commodities,
                'adminWeatherNotices' => $adminWeatherNotices,
                'recommendationNotifications' => $recommendationNotifications,
                'recommendationRuleKeys' => $recommendationRuleKeys,
                'recommendationSummary' => $recommendationSummary,
                'noticeCityTargets' => $noticeCityTargets,
                'noticeWeatherMatches' => $noticeWeatherMatches,
                'noticeRecipientStats' => $noticeRecipientStats,
                'statusTargetLabel' => $statusTargetLabel,
                'statusFocus' => $weatherRows->first(),
                'filters' => [
                    'panel' => $panel,
                    'province_id' => $statusProvinceId,
                    'status_province_id' => $statusProvinceId,
                    'status_city_id' => $statusCityId,
                    'status_q' => $statusQuery,
                    'commodity' => $commodity,
                    'notice_status' => $noticeStatus,
                    'notice_scope' => $noticeScope,
                    'notice_severity' => $noticeSeverity,
                    'notice_validity' => $noticeValidity,
                    'notice_province_id' => $noticeProvinceId,
                    'notice_city_id' => $noticeCityId,
                    'notice_q' => $noticeKeyword,
                    'automation_q' => $automationKeyword,
                    'automation_role_target' => $automationRoleTarget,
                    'automation_read_status' => $automationReadStatus,
                    'automation_rule_key' => $automationRuleKey,
                ],
            ],
            $viewModel
        ));
    }

    public function storeNotice(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('admin_weather_notices')) {
            return back()->withErrors([
                'weather_notice' => 'Tabel admin_weather_notices belum tersedia. Jalankan migrasi terlebih dahulu.',
            ]);
        }

        $payload = $this->validatedNoticePayload($request, true);
        if (array_key_exists('__errors', $payload)) {
            return back()->withErrors($payload['__errors'])->withInput();
        }

        $noticeId = (int) DB::table('admin_weather_notices')->insertGetId([
            'scope' => $payload['scope'],
            'province_id' => $payload['province_id'],
            'city_id' => $payload['city_id'],
            'district_id' => null,
            'severity' => $payload['severity'],
            'title' => $payload['title'],
            'message' => $payload['message'],
            'valid_until' => $payload['valid_until'],
            'is_active' => $payload['is_active'],
            'created_by' => $request->user()?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $noticePayload = $this->buildNoticeDispatchPayload($payload, $noticeId);
        $sentCount = $this->shouldDispatchWeatherNotice($noticePayload)
            ? $this->dispatchWeatherNoticeNotification($noticePayload)
            : 0;

        return back()->with('status', $this->noticeDispatchStatusMessage(
            'Notifikasi cuaca admin berhasil dikirim.',
            $noticePayload,
            $sentCount
        ));
    }

    public function updateNotice(Request $request, int $noticeId): RedirectResponse
    {
        if (! Schema::hasTable('admin_weather_notices')) {
            return back()->withErrors([
                'weather_notice' => 'Tabel admin_weather_notices belum tersedia. Jalankan migrasi terlebih dahulu.',
            ]);
        }

        $existing = DB::table('admin_weather_notices')
            ->where('id', $noticeId)
            ->first();

        if (! $existing) {
            return back()->withErrors([
                'weather_notice' => 'Notifikasi cuaca tidak ditemukan.',
            ]);
        }

        $payload = $this->validatedNoticePayload($request, false);
        if (array_key_exists('__errors', $payload)) {
            return back()->withErrors($payload['__errors'])->withInput();
        }

        DB::table('admin_weather_notices')
            ->where('id', $noticeId)
            ->update([
                'scope' => $payload['scope'],
                'province_id' => $payload['province_id'],
                'city_id' => $payload['city_id'],
                'district_id' => null,
                'severity' => $payload['severity'],
                'title' => $payload['title'],
                'message' => $payload['message'],
                'valid_until' => $payload['valid_until'],
                'is_active' => $payload['is_active'],
                'updated_at' => now(),
            ]);

        $noticePayload = $this->buildNoticeDispatchPayload($payload, $noticeId);
        $sentCount = $this->shouldDispatchWeatherNotice($noticePayload)
            ? $this->dispatchWeatherNoticeNotification($noticePayload)
            : 0;

        return back()->with('status', $this->noticeDispatchStatusMessage(
            'Notifikasi cuaca berhasil diperbarui.',
            $noticePayload,
            $sentCount
        ));
    }

    public function toggleNotice(int $noticeId): RedirectResponse
    {
        if (! Schema::hasTable('admin_weather_notices')) {
            return back()->withErrors([
                'weather_notice' => 'Tabel admin_weather_notices belum tersedia. Jalankan migrasi terlebih dahulu.',
            ]);
        }

        $notice = DB::table('admin_weather_notices')
            ->where('id', $noticeId)
            ->first();

        if (! $notice) {
            return back()->withErrors([
                'weather_notice' => 'Notifikasi cuaca tidak ditemukan.',
            ]);
        }

        $nextStatus = ! (bool) ($notice->is_active ?? false);

        DB::table('admin_weather_notices')
            ->where('id', $noticeId)
            ->update([
                'is_active' => $nextStatus,
                'updated_at' => now(),
            ]);

        if (! $nextStatus) {
            return back()->with('status', 'Notifikasi cuaca dinonaktifkan.');
        }

        $noticePayload = $this->buildNoticeDispatchPayload([
            'scope' => (string) ($notice->scope ?? 'global'),
            'province_id' => $notice->province_id ? (int) $notice->province_id : null,
            'city_id' => $notice->city_id ? (int) $notice->city_id : null,
            'severity' => (string) ($notice->severity ?? 'unknown'),
            'title' => $notice->title,
            'message' => (string) ($notice->message ?? ''),
            'valid_until' => $notice->valid_until,
            'is_active' => true,
            'audience_roles' => $this->inferAudienceRolesFromTitle((string) ($notice->title ?? '')),
        ], $noticeId);

        $sentCount = $this->shouldDispatchWeatherNotice($noticePayload)
            ? $this->dispatchWeatherNoticeNotification($noticePayload)
            : 0;

        return back()->with('status', $this->noticeDispatchStatusMessage(
            'Notifikasi cuaca diaktifkan kembali.',
            $noticePayload,
            $sentCount
        ));
    }

    public function destroyNotice(int $noticeId): RedirectResponse
    {
        if (! Schema::hasTable('admin_weather_notices')) {
            return back()->withErrors([
                'weather_notice' => 'Tabel admin_weather_notices belum tersedia. Jalankan migrasi terlebih dahulu.',
            ]);
        }

        DB::table('admin_weather_notices')
            ->where('id', $noticeId)
            ->delete();

        return back()->with('status', 'Notifikasi cuaca berhasil dihapus.');
    }

    public function deactivateExpiredNotices(): RedirectResponse
    {
        if (! Schema::hasTable('admin_weather_notices')) {
            return back()->withErrors([
                'weather_notice' => 'Tabel admin_weather_notices belum tersedia. Jalankan migrasi terlebih dahulu.',
            ]);
        }

        $affected = DB::table('admin_weather_notices')
            ->where('is_active', true)
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', now())
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        if ($affected < 1) {
            return back()->with('status', 'Tidak ada notifikasi expired yang perlu dinonaktifkan.');
        }

        return back()->with('status', "{$affected} notifikasi expired berhasil dinonaktifkan.");
    }

    public function pruneInactiveNotices(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('admin_weather_notices')) {
            return back()->withErrors([
                'weather_notice' => 'Tabel admin_weather_notices belum tersedia. Jalankan migrasi terlebih dahulu.',
            ]);
        }

        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $days = (int) ($validated['days'] ?? 90);
        $cutoff = now()->subDays($days);

        $affected = DB::table('admin_weather_notices')
            ->where('is_active', false)
            ->where('updated_at', '<=', $cutoff)
            ->delete();

        if ($affected < 1) {
            return back()->with('status', "Tidak ada notifikasi nonaktif lebih dari {$days} hari.");
        }

        return back()->with('status', "{$affected} notifikasi nonaktif lama berhasil dibersihkan.");
    }

    private function validatedNoticePayload(Request $request, bool $isCreate): array
    {
        $rules = [
            'scope' => ['required', 'in:global,province,city'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'severity' => ['required', 'in:green,yellow,red'],
            'title' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:500'],
            'valid_until' => ['required', 'date', 'after:now'],
            'is_active' => ['nullable', 'boolean'],
        ];

        $payload = $request->validate($rules);

        $scope = (string) $payload['scope'];
        $provinceTarget = null;
        $cityTarget = null;

        if ($scope === 'global') {
            $provinceTarget = null;
            $cityTarget = null;
        }

        if ($scope === 'province') {
            if (empty($payload['province_id'])) {
                return [
                    '__errors' => ['province_id' => 'Provinsi wajib dipilih untuk target notifikasi provinsi.'],
                ];
            }

            if (! empty($payload['city_id'])) {
                return [
                    '__errors' => ['city_id' => 'Untuk target provinsi, pilih hanya provinsi dan kosongkan kota.'],
                ];
            }

            $provinceTarget = (int) $payload['province_id'];
            $cityTarget = null;
        }

        if ($scope === 'city') {
            if (empty($payload['city_id'])) {
                return [
                    '__errors' => ['city_id' => 'Kota wajib dipilih untuk target notifikasi kota.'],
                ];
            }

            $city = DB::table('cities')
                ->select('id', 'province_id')
                ->where('id', (int) $payload['city_id'])
                ->first();

            if (! $city) {
                return [
                    '__errors' => ['city_id' => 'Data kota tidak ditemukan.'],
                ];
            }

            $cityTarget = (int) $city->id;
            $provinceTarget = (int) ($city->province_id ?? 0) ?: null;

            if (! empty($payload['province_id']) && (int) $payload['province_id'] !== $provinceTarget) {
                return [
                    '__errors' => ['city_id' => 'Kota yang dipilih tidak berada pada provinsi target.'],
                ];
            }
        }

        return [
            'scope' => $scope,
            'province_id' => $provinceTarget,
            'city_id' => $cityTarget,
            'severity' => (string) $payload['severity'],
            'title' => trim((string) ($payload['title'] ?? '')) ?: null,
            'message' => trim((string) $payload['message']),
            'valid_until' => $payload['valid_until'] ?? null,
            'is_active' => (bool) ($payload['is_active'] ?? $isCreate),
        ];
    }

    private function shouldDispatchWeatherNotice(array $noticePayload): bool
    {
        if (! ((bool) ($noticePayload['is_active'] ?? false))) {
            return false;
        }

        $validUntil = $noticePayload['valid_until'] ?? null;
        if (empty($validUntil)) {
            return true;
        }

        try {
            return Carbon::parse($validUntil)->greaterThanOrEqualTo(now());
        } catch (\Throwable $e) {
            return true;
        }
    }

    private function dispatchWeatherNoticeNotification(array $noticePayload): int
    {
        if (! Schema::hasTable('notifications') || ! Schema::hasTable('users')) {
            return 0;
        }

        $scope = (string) ($noticePayload['scope'] ?? 'global');
        $provinceId = $noticePayload['province_id'] ? (int) $noticePayload['province_id'] : null;
        $cityId = $noticePayload['city_id'] ? (int) $noticePayload['city_id'] : null;
        $severity = strtolower((string) ($noticePayload['severity'] ?? 'unknown'));
        $title = trim((string) ($noticePayload['title'] ?? ''));
        $message = trim((string) ($noticePayload['message'] ?? ''));
        $validUntil = $noticePayload['valid_until'] ?? null;
        $noticeId = isset($noticePayload['notice_id']) ? (int) $noticePayload['notice_id'] : null;
        $dispatchKey = trim((string) ($noticePayload['dispatch_key'] ?? ''));
        if ($dispatchKey === '') {
            $dispatchKey = $this->buildWeatherDispatchKey($noticePayload);
        }

        if ($message === '') {
            return 0;
        }

        $audienceRoles = $this->normalizeAudienceRoles($noticePayload['audience_roles'] ?? []);
        $query = User::query();
        if (! empty($audienceRoles)) {
            $query->whereInNormalizedRoles($audienceRoles);
        } else {
            $query->whereRaw('LOWER(TRIM(role)) <> ?', [User::normalizeRoleValue('admin')]);
        }

        if ($scope === 'city' && $cityId) {
            $query->where('city_id', $cityId);
        } elseif ($scope === 'province' && $provinceId) {
            $query->where('province_id', $provinceId);
        }

        $targetLabel = $this->resolveWeatherNoticeTargetLabel($scope, $provinceId, $cityId);
        $statusTitle = $title !== '' ? $title : $this->defaultWeatherNoticeTitle($severity);
        $statusActionUrl = route('landing') . '#fitur-cuaca';
        $validUntilLabel = null;
        if (! empty($validUntil)) {
            try {
                $validUntilLabel = Carbon::parse($validUntil)->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                $validUntilLabel = null;
            }
        }

        $sentCount = 0;
        $query->orderBy('id')->chunkById(200, function ($users) use (
            &$sentCount,
            $severity,
            $statusTitle,
            $message,
            $statusActionUrl,
            $scope,
            $targetLabel,
            $validUntilLabel,
            $noticeId,
            $dispatchKey
        ) {
            $userIds = $users->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $alreadyDispatched = DB::table('notifications')
                ->where('type', AdminWeatherNoticeNotification::class)
                ->where('notifiable_type', User::class)
                ->whereIn('notifiable_id', $userIds)
                ->where('data', 'like', '%"dispatch_key":"'.$dispatchKey.'"%')
                ->pluck('notifiable_id')
                ->map(fn ($id) => (int) $id)
                ->flip();

            foreach ($users as $user) {
                if (isset($alreadyDispatched[(int) $user->id])) {
                    continue;
                }

                $user->notify(new AdminWeatherNoticeNotification(
                    severity: $severity,
                    title: $statusTitle,
                    message: $message,
                    actionUrl: $statusActionUrl,
                    actionLabel: 'Buka Cuaca & Lokasi',
                    scope: $scope,
                    targetLabel: $targetLabel,
                    validUntil: $validUntilLabel,
                    noticeId: $noticeId,
                    dispatchKey: $dispatchKey
                ));
                $sentCount++;
            }
        });

        return $sentCount;
    }

    private function defaultWeatherNoticeTitle(string $severity): string
    {
        return match ($severity) {
            'red' => 'Siaga Tinggi Cuaca',
            'yellow' => 'Waspada Cuaca',
            'green' => 'Info Cuaca Stabil',
            default => 'Info Cuaca Operasional',
        };
    }

    private function resolveWeatherNoticeTargetLabel(string $scope, ?int $provinceId, ?int $cityId): string
    {
        if ($scope === 'city' && $cityId && Schema::hasTable('cities')) {
            $city = DB::table('cities')
                ->leftJoin('provinces', 'provinces.id', '=', 'cities.province_id')
                ->where('cities.id', $cityId)
                ->first([
                    'cities.name as city_name',
                    'cities.type as city_type',
                    'provinces.name as province_name',
                ]);

            if ($city) {
                $cityLabel = trim(((string) ($city->city_type ?? '')) . ' ' . ((string) ($city->city_name ?? '')));
                $provinceLabel = trim((string) ($city->province_name ?? ''));

                if ($cityLabel !== '' && $provinceLabel !== '') {
                    return $cityLabel . ', ' . $provinceLabel;
                }

                if ($cityLabel !== '') {
                    return $cityLabel;
                }
            }

            return 'Kota target';
        }

        if ($scope === 'province' && $provinceId && Schema::hasTable('provinces')) {
            $provinceName = DB::table('provinces')
                ->where('id', $provinceId)
                ->value('name');

            if (filled($provinceName)) {
                return 'Provinsi ' . $provinceName;
            }

            return 'Provinsi target';
        }

        return 'Semua lokasi';
    }

    private function noticeDispatchStatusMessage(string $baseMessage, array $noticePayload, int $sentCount): string
    {
        if (! ((bool) ($noticePayload['is_active'] ?? false))) {
            return $baseMessage;
        }

        if (! $this->shouldDispatchWeatherNotice($noticePayload)) {
            return $baseMessage . ' Notifikasi tidak diteruskan karena masa berlaku sudah lewat.';
        }

        if ($sentCount > 0) {
            $audienceLabel = $this->audienceLabelFromPayload($noticePayload);

            return $baseMessage . " Diteruskan ke {$sentCount} akun{$audienceLabel}.";
        }

        return $baseMessage . ' Tidak ada notifikasi baru yang perlu dikirim.';
    }

    private function buildNoticeDispatchPayload(array $payload, int $noticeId): array
    {
        $noticePayload = $payload;
        $noticePayload['notice_id'] = $noticeId;
        $noticePayload['dispatch_key'] = $this->buildWeatherDispatchKey($noticePayload);

        return $noticePayload;
    }

    private function buildWeatherDispatchKey(array $noticePayload): string
    {
        $validUntil = $noticePayload['valid_until'] ?? null;
        $normalizedValidUntil = null;
        if (! empty($validUntil)) {
            try {
                $normalizedValidUntil = Carbon::parse($validUntil)->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                $normalizedValidUntil = (string) $validUntil;
            }
        }

        $fingerprint = [
            'notice_id' => isset($noticePayload['notice_id']) ? (int) $noticePayload['notice_id'] : 0,
            'scope' => (string) ($noticePayload['scope'] ?? 'global'),
            'province_id' => $noticePayload['province_id'] ? (int) $noticePayload['province_id'] : null,
            'city_id' => $noticePayload['city_id'] ? (int) $noticePayload['city_id'] : null,
            'severity' => strtolower((string) ($noticePayload['severity'] ?? 'unknown')),
            'title' => trim((string) ($noticePayload['title'] ?? '')),
            'message' => trim((string) ($noticePayload['message'] ?? '')),
            'valid_until' => $normalizedValidUntil,
            'audience_roles' => $this->normalizeAudienceRoles($noticePayload['audience_roles'] ?? []),
        ];

        return sha1(json_encode($fingerprint, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  mixed  $roles
     * @return array<int, string>
     */
    private function normalizeAudienceRoles(mixed $roles): array
    {
        if (! is_array($roles)) {
            return [];
        }

        $allowed = ['consumer', 'seller', 'affiliate', 'mitra', 'admin'];

        return collect($roles)
            ->map(fn ($role) => strtolower(trim((string) $role)))
            ->filter(fn ($role) => in_array($role, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function inferAudienceRolesFromTitle(string $title): array
    {
        $normalizedTitle = strtoupper(trim($title));
        if (str_starts_with($normalizedTitle, '[AUTO][MITRA]')) {
            return ['mitra'];
        }

        return [];
    }

    private function audienceLabelFromPayload(array $noticePayload): string
    {
        $roles = $this->normalizeAudienceRoles($noticePayload['audience_roles'] ?? []);
        if ($roles === ['mitra']) {
            return ' mitra';
        }

        return '';
    }

    private function latestWeatherSnapshotForCity(string $kind, int $cityId): ?object
    {
        if ($cityId <= 0 || ! Schema::hasTable('weather_snapshots')) {
            return null;
        }

        return DB::table('weather_snapshots')
            ->where('provider', 'openweather')
            ->where('kind', $kind)
            ->where('location_type', 'city')
            ->where('location_id', $cityId)
            ->orderByDesc('fetched_at')
            ->first([
                'payload',
                'fetched_at',
                'valid_until',
            ]);
    }

    private function normalizeWeatherPayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (is_object($payload)) {
            return (array) $payload;
        }

        if (is_string($payload) && trim($payload) !== '') {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Ambil kode sumber dari payload cuaca current/forecast.
     */
    private function resolveWeatherSourceCode(array $current, array $forecast): string
    {
        $currentSource = strtolower(trim((string) data_get($current, 'source', '')));
        $forecastSource = strtolower(trim((string) data_get($forecast, 'source', '')));

        if ($currentSource === 'bmkg_fallback' || $forecastSource === 'bmkg_fallback') {
            return 'bmkg_fallback';
        }

        if ($currentSource === 'openweather' || $forecastSource === 'openweather') {
            return 'openweather';
        }

        return 'openweather';
    }

    /**
     * Format label sumber cuaca untuk tampilan admin.
     */
    private function weatherSourceLabel(string $sourceCode, bool $fromSnapshot): string
    {
        if ($sourceCode === 'bmkg_fallback') {
            return $fromSnapshot ? 'Snapshot BMKG Fallback' : 'BMKG Fallback';
        }

        return $fromSnapshot ? 'Snapshot OpenWeather' : 'OpenWeather';
    }

    /**
     * Resolve kandidat kode BMKG (adm4) per kota untuk debug fallback.
     *
     * @param  array<int, string>  $bmkgRegionCodeMap
     */
    private function resolveBmkgCodeForCity(int $cityId, array $bmkgRegionCodeMap): ?string
    {
        if ($cityId <= 0) {
            return null;
        }

        if (array_key_exists($cityId, $bmkgRegionCodeMap)) {
            return trim((string) $bmkgRegionCodeMap[$cityId]) ?: null;
        }

        // Fallback sementara jika kode adm4 kecamatan tidak tersedia.
        return (string) $cityId;
    }

    /**
     * Bangun alasan pemilihan sumber data cuaca untuk debugging operasional.
     */
    private function resolveWeatherSourceReason(
        bool $hasCoordinates,
        bool $hasWeatherPayload,
        string $sourceCode,
        bool $isLiveFetch,
        ?string $bmkgCode
    ): string {
        if (! $hasWeatherPayload) {
            if (! $hasCoordinates && $bmkgCode) {
                return 'Koordinat kota kosong. BMKG dicoba, tetapi tidak mengembalikan data.';
            }

            if (! $hasCoordinates) {
                return 'Koordinat kota kosong dan kode BMKG tidak tersedia.';
            }

            return 'OpenWeather tidak mengembalikan data valid untuk wilayah ini.';
        }

        if ($sourceCode === 'bmkg_fallback') {
            return ! $hasCoordinates
                ? 'Koordinat kota kosong, data berhasil diambil dari BMKG.'
                : 'OpenWeather invalid/kosong, sistem otomatis fallback ke BMKG.';
        }

        if (! $isLiveFetch) {
            return 'Data diambil dari snapshot cache OpenWeather.';
        }

        return 'OpenWeather valid dan dipakai sebagai sumber utama.';
    }

    private function isWeatherSnapshotStale(mixed $validUntil): bool
    {
        if (empty($validUntil)) {
            return true;
        }

        try {
            return Carbon::parse($validUntil)->isPast();
        } catch (\Throwable $e) {
            return true;
        }
    }

    private function normalizeLocationKeyword(string $keyword): string
    {
        $normalized = Str::lower(trim($keyword));
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? '';
        $normalized = preg_replace('/\s+/u', ' ', trim($normalized)) ?? '';

        return (string) $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function locationKeywordTokens(string $normalizedKeyword): array
    {
        if ($normalizedKeyword === '') {
            return [];
        }

        $tokens = explode(' ', $normalizedKeyword);

        return collect($tokens)
            ->map(fn ($token) => trim((string) $token))
            ->filter(fn ($token) => $token !== '')
            ->unique()
            ->take(6)
            ->values()
            ->all();
    }

    private function applyLocationKeywordWhere($query, string $keyword): void
    {
        $like = '%' . $keyword . '%';

        $query->where(function ($locationQuery) use ($like) {
            $locationQuery->whereRaw('LOWER(cities.name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(cities.type) LIKE ?', [$like])
                ->orWhereRaw('LOWER(provinces.name) LIKE ?', [$like]);
        });
    }

    private function applyNoticeKeywordWhere($query, string $keyword, array $keywordSeverities = []): void
    {
        $like = '%' . $keyword . '%';

        $query->where(function ($noticeQuery) use ($like, $keywordSeverities) {
            $noticeQuery->whereRaw('LOWER(COALESCE(admin_weather_notices.title, \'\')) LIKE ?', [$like])
                ->orWhereRaw('LOWER(admin_weather_notices.message) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(provinces.name, \'\')) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(cities.name, \'\')) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(cities.type, \'\')) LIKE ?', [$like]);

            if (! empty($keywordSeverities)) {
                $noticeQuery->orWhereIn('admin_weather_notices.severity', $keywordSeverities);
            }
        });
    }

    /**
     * @param  array<int, string>  $tokens
     * @return array<int, string>
     */
    private function inferWeatherSeveritiesByKeyword(string $normalizedKeyword, array $tokens = []): array
    {
        $dictionary = [
            'red' => ['bahaya', 'siaga', 'ekstrem', 'ekstrim', 'darurat', 'banjir', 'badai', 'puting', 'longsor'],
            'yellow' => ['waspada', 'hujan', 'angin', 'panas', 'cuaca', 'buruk', 'mendung', 'petir'],
            'green' => ['aman', 'normal', 'stabil', 'cerah'],
        ];

        $haystacks = collect([$normalizedKeyword])
            ->merge($tokens)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values();

        if ($haystacks->isEmpty()) {
            return [];
        }

        $matched = collect();
        foreach ($dictionary as $severity => $keywords) {
            foreach ($haystacks as $haystack) {
                foreach ($keywords as $keyword) {
                    if (str_contains($haystack, $keyword)) {
                        $matched->push($severity);
                        continue 3;
                    }
                }
            }
        }

        return $matched->unique()->values()->all();
    }

    private function weatherSeverityRank(string $severity): int
    {
        return match (strtolower($severity)) {
            'red' => 3,
            'yellow' => 2,
            'green' => 1,
            default => 0,
        };
    }

}
