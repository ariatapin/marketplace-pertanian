<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SettingsPageController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        // CATATAN-AUDIT: Halaman settings lama dialihkan ke Marketplace > Konten agar alur admin lebih sederhana.
        return redirect()->route('admin.modules.marketplace', ['section' => 'content']);
    }

    public function updateMitraSubmission(Request $request): RedirectResponse
    {
        // CATATAN-AUDIT: Flag accept_mitra mengontrol akses pengajuan Mitra via banner/promo di landing page.
        $payload = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        if (! Schema::hasTable('feature_flags')) {
            return back()->withErrors([
                'feature_flags' => 'Tabel feature_flags belum tersedia. Jalankan migrasi terlebih dahulu.',
            ]);
        }

        DB::table('feature_flags')->updateOrInsert(
            ['key' => 'accept_mitra'],
            [
                'is_enabled' => (bool) ($payload['is_enabled'] ?? false),
                'description' => trim((string) ($payload['description'] ?? '')) ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return back()->with('status', 'Status pengajuan mitra berhasil diperbarui.');
    }

    public function updateRoleAutomation(Request $request): RedirectResponse
    {
        // CATATAN-AUDIT: Flag automation_role_cycle mengontrol auto-run berkala sistem simulasi role.
        $payload = $request->validate([
            'automation_enabled' => ['nullable', 'boolean'],
            'automation_description' => ['nullable', 'string', 'max:500'],
        ]);

        if (! Schema::hasTable('feature_flags')) {
            return back()->withErrors([
                'feature_flags' => 'Tabel feature_flags belum tersedia. Jalankan migrasi terlebih dahulu.',
            ]);
        }

        DB::table('feature_flags')->updateOrInsert(
            ['key' => 'automation_role_cycle'],
            [
                'is_enabled' => (bool) ($payload['automation_enabled'] ?? false),
                'description' => trim((string) ($payload['automation_description'] ?? '')) ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return back()->with('status', 'Status otomasi role berhasil diperbarui.');
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        // CATATAN-AUDIT: Konten promo/banner landing disimpan di marketplace_announcements.
        $data = $this->validateAnnouncement($request);

        if (! Schema::hasTable('marketplace_announcements')) {
            return back()->withErrors([
                'announcements' => 'Tabel marketplace_announcements belum tersedia. Jalankan migrasi terlebih dahulu.',
            ]);
        }

        $hasImageColumn = Schema::hasColumn('marketplace_announcements', 'image_url');
        $storedImagePath = null;
        if ($hasImageColumn && $request->hasFile('image')) {
            $storedImagePath = $request->file('image')->store('marketplace/announcements', 'public');
        }

        $payload = [
            'type' => $data['type'],
            'title' => $data['title'],
            'message' => $data['message'],
            'cta_label' => $data['cta_label'],
            'cta_url' => $data['cta_url'],
            'is_active' => $data['is_active'],
            'sort_order' => $data['sort_order'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if ($hasImageColumn) {
            $payload['image_url'] = $storedImagePath;
        }

        DB::table('marketplace_announcements')->insert($payload);

        return back()->with('status', 'Konten landing berhasil ditambahkan.');
    }

    public function updateAnnouncement(Request $request, int $announcementId): RedirectResponse
    {
        $data = $this->validateAnnouncement($request);

        if (! Schema::hasTable('marketplace_announcements')) {
            return back()->withErrors([
                'announcements' => 'Tabel marketplace_announcements belum tersedia. Jalankan migrasi terlebih dahulu.',
            ]);
        }

        $hasImageColumn = Schema::hasColumn('marketplace_announcements', 'image_url');
        $existing = DB::table('marketplace_announcements')
            ->where('id', $announcementId)
            ->first($hasImageColumn ? ['id', 'image_url'] : ['id']);

        if (! $existing) {
            return back()->withErrors([
                'announcements' => 'Konten landing tidak ditemukan.',
            ]);
        }

        $nextImagePath = $hasImageColumn ? $this->normalizeStoredImagePath((string) ($existing->image_url ?? '')) : null;
        $shouldRemoveImage = $hasImageColumn && (bool) ($data['remove_image'] ?? false);

        if ($hasImageColumn && $request->hasFile('image')) {
            $newImagePath = $request->file('image')->store('marketplace/announcements', 'public');
            if ($nextImagePath) {
                Storage::disk('public')->delete($nextImagePath);
            }
            $nextImagePath = $newImagePath;
        } elseif ($shouldRemoveImage && $nextImagePath) {
            Storage::disk('public')->delete($nextImagePath);
            $nextImagePath = null;
        }

        $payload = [
            'type' => $data['type'],
            'title' => $data['title'],
            'message' => $data['message'],
            'cta_label' => $data['cta_label'],
            'cta_url' => $data['cta_url'],
            'is_active' => $data['is_active'],
            'sort_order' => $data['sort_order'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'updated_by' => $request->user()?->id,
            'updated_at' => now(),
        ];
        if ($hasImageColumn) {
            $payload['image_url'] = $nextImagePath;
        }

        DB::table('marketplace_announcements')
            ->where('id', $announcementId)
            ->update($payload);

        return back()->with('status', 'Konten landing berhasil diperbarui.');
    }

    public function destroyAnnouncement(int $announcementId): RedirectResponse
    {
        if (! Schema::hasTable('marketplace_announcements')) {
            return back()->withErrors([
                'announcements' => 'Tabel marketplace_announcements belum tersedia. Jalankan migrasi terlebih dahulu.',
            ]);
        }

        $hasImageColumn = Schema::hasColumn('marketplace_announcements', 'image_url');
        $existing = DB::table('marketplace_announcements')
            ->where('id', $announcementId)
            ->first($hasImageColumn ? ['id', 'image_url'] : ['id']);

        if ($existing && $hasImageColumn) {
            $storedPath = $this->normalizeStoredImagePath((string) ($existing->image_url ?? ''));
            if ($storedPath) {
                Storage::disk('public')->delete($storedPath);
            }
        }

        DB::table('marketplace_announcements')
            ->where('id', $announcementId)
            ->delete();

        return back()->with('status', 'Konten landing berhasil dihapus.');
    }

    private function validateAnnouncement(Request $request): array
    {
        $validated = $request->validate([
            'type' => ['required', 'in:banner,promo,info'],
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:500'],
            'cta_label' => ['nullable', 'string', 'max:40'],
            'cta_url' => ['nullable', 'string', 'max:255', 'regex:/^(https?:\/\/|\/).+/'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
            'remove_image' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        return [
            'type' => $validated['type'],
            'title' => trim((string) $validated['title']),
            'message' => trim((string) $validated['message']),
            'cta_label' => trim((string) ($validated['cta_label'] ?? '')) ?: null,
            'cta_url' => trim((string) ($validated['cta_url'] ?? '')) ?: null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'starts_at' => $this->normalizeDateTimeValue($validated['starts_at'] ?? null),
            'ends_at' => $this->normalizeDateTimeValue($validated['ends_at'] ?? null),
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'remove_image' => (bool) ($validated['remove_image'] ?? false),
        ];
    }

    private function normalizeDateTimeValue(?string $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeStoredImagePath(string $rawPath): ?string
    {
        $path = trim($rawPath);
        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return null;
        }

        if (Str::startsWith($path, '/storage/')) {
            return ltrim(Str::replaceFirst('/storage/', '', $path), '/');
        }

        if (Str::startsWith($path, 'storage/')) {
            return ltrim(Str::replaceFirst('storage/', '', $path), '/');
        }

        return ltrim($path, '/');
    }
}
