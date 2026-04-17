<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AffiliateCommissionPolicyService;
use App\Services\AffiliateLockPolicyService;
use App\Services\AffiliateReferralTrackingService;
use App\Services\FeatureFlagService;
use App\Support\AdminMarketplaceViewModelFactory;
use App\Support\AdminSettingsViewModelFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MarketplacePageController extends Controller
{
    public function __construct(
        protected AdminMarketplaceViewModelFactory $marketplaceViewModelFactory,
        protected FeatureFlagService $featureFlags,
        protected AdminSettingsViewModelFactory $settingsViewModelFactory,
        protected AffiliateReferralTrackingService $affiliateTracking,
        protected AffiliateLockPolicyService $affiliateLockPolicy,
        protected AffiliateCommissionPolicyService $affiliateCommissionPolicy
    ) {}

    public function __invoke(Request $request)
    {
        $section = $request->string('section')->toString();
        if (! in_array($section, ['overview', 'content'], true)) {
            $section = 'overview';
        }

        $summary = [
            'announcements_total' => 0,
            'announcements_active' => 0,
            'promos_active' => 0,
            'banners_active' => 0,
            'mitra_submission_open' => false,
            'role_automation_enabled' => false,
            'mitra_pending' => 0,
            'mitra_approved' => 0,
            'mitra_rejected' => 0,
        ];
        $affiliateTrackingSummary = [
            'total_clicks' => 0,
            'total_add_to_cart' => 0,
            'total_checkout_created' => 0,
            'total_completed_orders' => 0,
            'conversion_checkout_percent' => 0.0,
            'conversion_completed_percent' => 0.0,
        ];

        $marketplaceRows = collect();
        $announcementRows = collect();
        $settingsAnnouncementRows = collect();
        $topAffiliateTracking = collect();
        $affiliateLockPolicy = $this->affiliateLockPolicy->resolve();
        $affiliateCommissionRange = $this->affiliateCommissionPolicy->resolveRange();

        if (Schema::hasTable('marketplace_announcements')) {
            $settingsAnnouncementRows = DB::table('marketplace_announcements')
                ->orderByDesc('is_active')
                ->orderBy('sort_order')
                ->orderByDesc('updated_at')
                ->get();

            $activeAnnouncementScope = function ($query): void {
                $query->where('is_active', true)
                    ->where(function ($timeQuery) {
                        $timeQuery->whereNull('starts_at')
                            ->orWhere('starts_at', '<=', now());
                    })
                    ->where(function ($timeQuery) {
                        $timeQuery->whereNull('ends_at')
                            ->orWhere('ends_at', '>=', now());
                    });
            };

            $summary['announcements_total'] = DB::table('marketplace_announcements')->count();
            $summary['announcements_active'] = DB::table('marketplace_announcements')
                ->where($activeAnnouncementScope)
                ->count();
            $summary['promos_active'] = DB::table('marketplace_announcements')
                ->where('type', 'promo')
                ->where($activeAnnouncementScope)
                ->count();
            $summary['banners_active'] = DB::table('marketplace_announcements')
                ->where('type', 'banner')
                ->where($activeAnnouncementScope)
                ->count();

            if (in_array($section, ['overview', 'content'], true)) {
                $announcementRows = DB::table('marketplace_announcements')
                    ->leftJoin('users as updaters', 'updaters.id', '=', 'marketplace_announcements.updated_by')
                    ->select([
                        'marketplace_announcements.id',
                        'marketplace_announcements.type',
                        'marketplace_announcements.title',
                        'marketplace_announcements.is_active',
                        'marketplace_announcements.starts_at',
                        'marketplace_announcements.ends_at',
                        'marketplace_announcements.updated_at',
                        'updaters.name as updated_by_name',
                    ])
                    ->orderByDesc('marketplace_announcements.is_active')
                    ->orderBy('marketplace_announcements.sort_order')
                    ->orderByDesc('marketplace_announcements.updated_at')
                    ->limit(10)
                    ->get();
            }
        }

        $summary['mitra_submission_open'] = $this->featureFlags->isEnabled('accept_mitra', false);
        $summary['role_automation_enabled'] = $this->featureFlags->isEnabled('automation_role_cycle', false);
        if (Schema::hasTable('mitra_applications')) {
            $summary['mitra_pending'] = DB::table('mitra_applications')->where('status', 'pending')->count();
            $summary['mitra_approved'] = DB::table('mitra_applications')->where('status', 'approved')->count();
            $summary['mitra_rejected'] = DB::table('mitra_applications')->where('status', 'rejected')->count();
        }

        $affiliateTrackingSummary = array_merge(
            $affiliateTrackingSummary,
            $this->affiliateTracking->summaryForAdmin()
        );
        $topAffiliateTracking = $this->affiliateTracking
            ->topAffiliatesForAdmin(5)
            ->map(function ($row): array {
                return [
                    'affiliate_id' => (int) ($row->affiliate_id ?? 0),
                    'name' => (string) (($row->name ?? '') !== '' ? $row->name : '-'),
                    'email' => (string) (($row->email ?? '') !== '' ? $row->email : '-'),
                    'completed_orders' => (int) ($row->completed_orders ?? 0),
                    'total_commission_label' => 'Rp' . number_format((float) ($row->total_commission ?? 0), 0, ',', '.'),
                ];
            })
            ->values();

        $settingsViewModel = $this->settingsViewModelFactory->make(
            mitraFlag: [
                'is_enabled' => (bool) ($summary['mitra_submission_open'] ?? false),
                'description' => (string) $this->featureFlags->description('accept_mitra', ''),
            ],
            announcements: $settingsAnnouncementRows,
            automationFlag: [
                'is_enabled' => (bool) ($summary['role_automation_enabled'] ?? false),
                'description' => (string) $this->featureFlags->description('automation_role_cycle', ''),
            ]
        );

        $viewModel = $this->marketplaceViewModelFactory->make(
            summary: $summary,
            marketplaceRows: $marketplaceRows,
            notificationRows: collect(),
            announcementRows: $announcementRows
        );

        return view('admin.marketplace', array_merge(
            [
                'activeSection' => $section,
                'affiliateTrackingSummary' => $affiliateTrackingSummary,
                'topAffiliateTracking' => $topAffiliateTracking,
                'affiliateLockPolicy' => $affiliateLockPolicy,
                'affiliateCommissionRange' => $affiliateCommissionRange,
            ],
            $viewModel,
            $settingsViewModel
        ));
    }

    public function updateAffiliateLockPolicy(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'cooldown_enabled' => ['nullable', 'boolean'],
            'lock_days' => ['required', 'integer', 'min:1', 'max:365'],
            'refresh_on_repromote' => ['nullable', 'boolean'],
        ]);

        if (! Schema::hasTable('feature_flags')) {
            return back()->withErrors([
                'feature_flags' => 'Tabel feature_flags belum tersedia. Jalankan migrasi terlebih dahulu.',
            ]);
        }

        $this->affiliateLockPolicy->save(
            cooldownEnabled: (bool) ($payload['cooldown_enabled'] ?? false),
            lockDays: (int) ($payload['lock_days'] ?? 30),
            refreshOnRepromote: (bool) ($payload['refresh_on_repromote'] ?? false)
        );

        return redirect()
            ->route('admin.modules.marketplace', ['section' => 'overview'])
            ->with('status', 'Aturan lock affiliate berhasil diperbarui.');
    }

    public function updateAffiliateCommissionRange(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'affiliate_commission_min_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'affiliate_commission_max_percent' => ['required', 'numeric', 'min:0', 'max:100', 'gte:affiliate_commission_min_percent'],
        ], [
            'affiliate_commission_max_percent.gte' => 'Komisi maksimal harus lebih besar atau sama dengan komisi minimal.',
        ]);

        $adminUser = User::query()
            ->whereNormalizedRole('admin')
            ->orderBy('id')
            ->first(['id']);

        $targetAdminUserId = (int) ($adminUser?->id ?? ($request->user()?->id ?? 0));
        $saved = $this->affiliateCommissionPolicy->persistRange(
            $targetAdminUserId,
            (float) $validated['affiliate_commission_min_percent'],
            (float) $validated['affiliate_commission_max_percent']
        );

        if (! $saved) {
            return back()->withErrors([
                'affiliate_commission_range' => 'Pengaturan komisi affiliate belum tersedia. Jalankan migration terbaru.',
            ]);
        }

        $updatedRange = $this->affiliateCommissionPolicy->resolveRange();

        return redirect()
            ->route('admin.modules.marketplace', ['section' => 'overview'])
            ->with('status', sprintf(
                'Batas komisi affiliate disimpan: %s%% sampai %s%%.',
                $this->affiliateCommissionPolicy->formatPercent((float) ($updatedRange['min'] ?? 0)),
                $this->affiliateCommissionPolicy->formatPercent((float) ($updatedRange['max'] ?? 100))
            ));
    }
}
