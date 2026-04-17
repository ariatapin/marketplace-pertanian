<?php

namespace App\Http\Controllers;

use App\Services\FeatureFlagService;
use App\Support\MitraApplicationStatusNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MitraApplicationController extends Controller
{
    public function __construct(protected FeatureFlagService $featureFlags) {}

    public function entryFromBanner(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user?->isConsumer()) {
            $this->auditBannerEntry($request, $user?->id, 'blocked', 'role_not_consumer');
            abort(403, 'Hanya consumer yang dapat mengakses program mitra.');
        }

        if (! $this->featureFlags->isEnabled('accept_mitra', false)) {
            $this->auditBannerEntry($request, $user->id, 'blocked', 'feature_flag_closed');
            return redirect()
                ->route('landing')
                ->with('error', 'Pengajuan mitra B2B belum dibuka oleh admin.');
        }

        $request->session()->put('mitra_program_access_until', now()->addMinutes(20)->timestamp);
        $this->auditBannerEntry($request, $user->id, 'granted', 'banner_access_ok');

        return redirect()->route('program.mitra.form');
    }

    public function form(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user?->isConsumer()) {
            abort(403, 'Hanya consumer yang dapat mengakses program mitra.');
        }

        $accessRedirect = $this->ensureAccessFromBanner($request);
        if ($accessRedirect) {
            return $accessRedirect;
        }

        $mitraApplication = null;
        if (Schema::hasTable('mitra_applications')) {
            $mitraApplication = DB::table('mitra_applications')
                ->where('user_id', $user->id)
                ->first();
        }

        $mitraNotifications = collect();
        $unreadMitraNotificationCount = 0;
        if (Schema::hasTable('notifications')) {
            $mitraNotifications = $user->notifications()
                ->where('type', MitraApplicationStatusNotification::class)
                ->latest()
                ->limit(8)
                ->get();

            $unreadMitraNotificationCount = $user->unreadNotifications()
                ->where('type', MitraApplicationStatusNotification::class)
                ->count();
        }

        return view('marketplace.mitra-program', [
            'user' => $user,
            'mitraApplication' => $mitraApplication,
            'mitraModeWarning' => $this->buildMitraModeWarning($user),
            'mitraSubmissionOpen' => $this->featureFlags->isEnabled('accept_mitra', false),
            'mitraSubmissionNote' => $this->featureFlags->description('accept_mitra'),
            'mitraNotifications' => $mitraNotifications,
            'unreadMitraNotificationCount' => $unreadMitraNotificationCount,
            'notificationCount' => Schema::hasTable('notifications')
                ? (int) $user->unreadNotifications()->count()
                : 0,
        ]);
    }

    public function storeOrSubmit(Request $request): RedirectResponse
    {
        $user = $request->user();
        $action = strtolower((string) $request->input('action', 'draft'));
        $isSubmit = $action === 'submit';

        if (! $user?->isConsumer()) {
            abort(403, 'Hanya consumer yang dapat mengajukan mitra.');
        }

        $accessRedirect = $this->ensureAccessFromBanner($request);
        if ($accessRedirect) {
            return $accessRedirect;
        }

        if (! Schema::hasTable('mitra_applications')) {
            return back()->withErrors([
                'mitra_application' => 'Tabel mitra_applications belum tersedia. Jalankan migrasi terlebih dahulu.',
            ]);
        }

        if (! $this->featureFlags->isEnabled('accept_mitra', false)) {
            return back()->withErrors([
                'mitra_application' => 'Pengajuan mitra B2B sedang ditutup oleh admin.',
            ]);
        }

        if (! in_array($action, ['draft', 'submit'], true)) {
            return back()->withErrors([
                'mitra_application' => 'Aksi pengajuan tidak valid.',
            ]);
        }

        $existing = DB::table('mitra_applications')
            ->where('user_id', $user->id)
            ->first();
        $hasSubmittedAt = Schema::hasColumn('mitra_applications', 'submitted_at');

        if ($existing && $existing->status === 'approved') {
            return back()->withErrors([
                'mitra_application' => 'Pengajuan Anda sudah disetujui. Akun sudah memiliki akses mitra.',
            ]);
        }

        if ($existing && $existing->status === 'pending') {
            return back()->withErrors([
                'mitra_application' => 'Pengajuan Anda sedang direview admin. Tunggu hasil review.',
            ]);
        }

        $data = $this->validatePayload($request, $isSubmit, $existing);
        $storedDocs = $this->storeDocuments($request, $user->id, $existing);

        DB::transaction(function () use ($user, $existing, $data, $storedDocs, $isSubmit, $hasSubmittedAt) {
            $payload = array_merge($data, $storedDocs, [
                'status' => $isSubmit ? 'pending' : 'draft',
                'decided_by' => null,
                'decided_at' => null,
                'notes' => null,
                'updated_at' => now(),
            ]);

            if ($hasSubmittedAt) {
                $payload['submitted_at'] = $isSubmit ? now() : null;
            }

            if ($existing) {
                DB::table('mitra_applications')
                    ->where('id', $existing->id)
                    ->update($payload);
            } else {
                DB::table('mitra_applications')
                    ->insert($payload + [
                        'user_id' => $user->id,
                        'created_at' => now(),
                    ]);
            }
        });

        if ($isSubmit && Schema::hasTable('notifications')) {
            $user->notify(new MitraApplicationStatusNotification(
                status: 'pending',
                title: 'Pengajuan Mitra Sedang Direview',
                message: 'Pengajuan mitra B2B Anda berhasil dikirim dan sedang dalam proses review admin.',
                actionUrl: route('program.mitra.form') . '#mitra-notifications',
                actionLabel: 'Lihat Status Pengajuan'
            ));
        }

        return back()->with(
            'status',
            $isSubmit ? 'Pengajuan mitra B2B berhasil dikirim. Tim admin akan meninjau dokumen Anda.' : 'Draft pengajuan mitra B2B berhasil disimpan.'
        );
    }

    private function validatePayload(Request $request, bool $isSubmit, ?object $existing): array
    {
        $baseRules = [
            'full_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120'],
            'region_id' => ['nullable', 'integer'],
            'warehouse_address' => ['nullable', 'string', 'max:500'],
            'warehouse_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'warehouse_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'products_managed' => ['nullable', 'string', 'max:800'],
            'warehouse_capacity' => ['nullable', 'integer', 'min:1'],
            'ktp_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'npwp_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'nib_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'warehouse_photo_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'certification_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];

        $validated = $request->validate($baseRules);

        if (! $isSubmit) {
            return [
                'full_name' => trim((string) ($validated['full_name'] ?? '')),
                'email' => trim((string) ($validated['email'] ?? '')),
                'region_id' => $validated['region_id'] ?? null,
                'warehouse_address' => trim((string) ($validated['warehouse_address'] ?? '')) ?: null,
                'warehouse_lat' => $validated['warehouse_lat'] ?? null,
                'warehouse_lng' => $validated['warehouse_lng'] ?? null,
                'products_managed' => trim((string) ($validated['products_managed'] ?? '')) ?: null,
                'warehouse_capacity' => $validated['warehouse_capacity'] ?? null,
                'special_certification_url' => $existing?->special_certification_url ?? null,
            ];
        }

        $payload = [
            'full_name' => trim((string) ($validated['full_name'] ?? '')),
            'email' => trim((string) ($validated['email'] ?? '')),
            'region_id' => $validated['region_id'] ?? null,
            'warehouse_address' => trim((string) ($validated['warehouse_address'] ?? '')) ?: null,
            'warehouse_lat' => $validated['warehouse_lat'] ?? null,
            'warehouse_lng' => $validated['warehouse_lng'] ?? null,
            'products_managed' => trim((string) ($validated['products_managed'] ?? '')) ?: null,
            'warehouse_capacity' => $validated['warehouse_capacity'] ?? null,
            'special_certification_url' => $existing?->special_certification_url ?? null,
        ];

        if (empty($payload['warehouse_address'])) {
            throw ValidationException::withMessages(['warehouse_address' => 'Alamat gudang wajib diisi saat submit pengajuan.']);
        }

        if (empty($payload['products_managed'])) {
            throw ValidationException::withMessages(['products_managed' => 'Produk yang dikelola wajib diisi saat submit pengajuan.']);
        }

        if (empty($payload['warehouse_capacity'])) {
            throw ValidationException::withMessages(['warehouse_capacity' => 'Kapasitas gudang wajib diisi saat submit pengajuan.']);
        }

        $requiredDocuments = [
            'ktp_url' => ['field' => 'ktp_file', 'label' => 'Dokumen KTP'],
            'npwp_url' => ['field' => 'npwp_file', 'label' => 'Dokumen NPWP'],
            'nib_url' => ['field' => 'nib_file', 'label' => 'Dokumen NIB'],
            'warehouse_building_photo_url' => ['field' => 'warehouse_photo_file', 'label' => 'Foto bangunan gudang'],
        ];

        foreach ($requiredDocuments as $column => $meta) {
            $hasExisting = !empty((string) ($existing?->{$column} ?? ''));
            $hasNewFile = $request->hasFile($meta['field']);
            if (! $hasExisting && ! $hasNewFile) {
                throw ValidationException::withMessages([
                    $meta['field'] => $meta['label'] . ' wajib diunggah sebelum submit.',
                ]);
            }
        }

        return $payload;
    }

    private function storeDocuments(Request $request, int $userId, ?object $existing): array
    {
        $map = [
            'ktp_file' => 'ktp_url',
            'npwp_file' => 'npwp_url',
            'nib_file' => 'nib_url',
            'warehouse_photo_file' => 'warehouse_building_photo_url',
            'certification_file' => 'special_certification_url',
        ];

        $payload = [];
        foreach ($map as $inputName => $column) {
            $payload[$column] = $existing?->{$column} ?? null;
            if ($request->hasFile($inputName)) {
                $path = $request->file($inputName)->store('mitra-applications/' . $userId, 'public');
                $payload[$column] = $path;
            }
        }

        return $payload;
    }

    private function ensureAccessFromBanner(Request $request): ?RedirectResponse
    {
        $validUntil = (int) $request->session()->get('mitra_program_access_until', 0);
        if ($validUntil >= now()->timestamp) {
            return null;
        }

        $request->session()->forget('mitra_program_access_until');

        return redirect()
            ->route('landing')
            ->with('error', 'Akses form mitra hanya tersedia melalui klik banner promo mitra yang aktif.');
    }

    private function auditBannerEntry(Request $request, ?int $userId, string $status, string $reason): void
    {
        if (! Schema::hasTable('mitra_banner_entry_audits')) {
            return;
        }

        $expiresAt = null;
        $expiresTimestamp = $request->query('expires');
        if (is_numeric($expiresTimestamp)) {
            $expiresAt = now()->setTimestamp((int) $expiresTimestamp);
        }

        DB::table('mitra_banner_entry_audits')->insert([
            'user_id' => $userId,
            'status' => $status,
            'reason' => $reason,
            'entry_source' => 'banner',
            'announcement_id' => $request->filled('announcement_id')
                ? (int) $request->query('announcement_id')
                : null,
            'signed_expires_at' => $expiresAt,
            'ip_address' => substr((string) $request->ip(), 0, 45),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'session_id' => $request->hasSession() ? (string) $request->session()->getId() : null,
            'request_url' => substr((string) $request->fullUrl(), 0, 2000),
            'created_at' => now(),
        ]);
    }

    private function buildMitraModeWarning(object $user): array
    {
        $default = [
            'has_active_mode' => false,
            'mode' => null,
            'mode_label' => null,
            'message' => null,
        ];

        if (! Schema::hasTable('consumer_profiles')) {
            return $default;
        }

        $profile = DB::table('consumer_profiles')
            ->where('user_id', (int) ($user->id ?? 0))
            ->first(['mode', 'mode_status']);

        $mode = strtolower(trim((string) ($profile->mode ?? '')));
        $modeStatus = strtolower(trim((string) ($profile->mode_status ?? '')));

        if ($modeStatus !== 'approved' || ! in_array($mode, ['affiliate', 'farmer_seller'], true)) {
            return $default;
        }

        $modeLabel = $mode === 'affiliate' ? 'Affiliate' : 'Penjual P2P';

        return [
            'has_active_mode' => true,
            'mode' => $mode,
            'mode_label' => $modeLabel,
            'message' => "Akun Anda sedang aktif sebagai {$modeLabel}. Jika pengajuan Mitra disetujui, mode {$modeLabel} akan dinonaktifkan dan akses/rekap terkait mode tersebut disesuaikan ulang.",
        ];
    }
}
