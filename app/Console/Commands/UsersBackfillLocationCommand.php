<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UsersBackfillLocationCommand extends Command
{
    protected $signature = 'users:backfill-location
        {--dry-run : Simulasikan proses tanpa update ke database}
        {--all : Proses semua user (default hanya user yang lokasinya belum lengkap)}
        {--limit=0 : Batasi jumlah user yang diproses (0 = tanpa batas)}';

    protected $description = 'Lengkapi lokasi user (province_id/city_id/district_id/lat/lng) secara aman dan idempotent.';

    public function handle(): int
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('cities') || ! Schema::hasTable('provinces')) {
            $this->error('Tabel users/cities/provinces wajib tersedia sebelum backfill lokasi dijalankan.');
            return self::FAILURE;
        }

        $cityRows = DB::table('cities')
            ->select('id', 'province_id', 'lat', 'lng')
            ->orderBy('id')
            ->get();

        if ($cityRows->isEmpty()) {
            $this->error('Tidak ada data kota. Jalankan seeder wilayah terlebih dahulu.');
            return self::FAILURE;
        }

        $cityById = $cityRows->keyBy(fn ($city) => (int) $city->id);
        $defaultCity = $cityRows
            ->first(fn ($city) => $city->lat !== null && $city->lng !== null)
            ?? $cityRows->first();

        $firstCityByProvince = $cityRows
            ->groupBy(fn ($city) => (int) ($city->province_id ?? 0))
            ->map(function ($rows) {
                return collect($rows)
                    ->sortBy(fn ($city) => ($city->lat === null || $city->lng === null) ? 1 : 0)
                    ->first();
            });

        $districtCityById = collect();
        if (Schema::hasTable('districts')) {
            $districtCityById = DB::table('districts')
                ->select('id', 'city_id')
                ->get()
                ->mapWithKeys(fn ($row) => [(int) $row->id => (int) $row->city_id]);
        }

        $dryRun = (bool) $this->option('dry-run');
        $processAll = (bool) $this->option('all');
        $limit = max(0, (int) $this->option('limit'));

        $this->info('Mulai backfill lokasi user...');
        $this->line('Mode: ' . ($dryRun ? 'DRY RUN' : 'UPDATE DB'));
        $this->line('Scope: ' . ($processAll ? 'semua user' : 'user dengan lokasi belum lengkap'));
        if ($limit > 0) {
            $this->line("Limit: {$limit} user");
        }

        $query = DB::table('users')
            ->select('id', 'email', 'role', 'province_id', 'city_id', 'district_id', 'lat', 'lng')
            ->orderBy('id');

        if (! $processAll) {
            $query->where(function ($where) {
                $where->whereNull('province_id')
                    ->orWhereNull('city_id')
                    ->orWhereNull('lat')
                    ->orWhereNull('lng');
            });
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $users = $query->get();
        if ($users->isEmpty()) {
            $this->info('Tidak ada user yang perlu diproses.');
            return self::SUCCESS;
        }

        $stats = [
            'processed' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'fallback_city' => 0,
            'province_city_sync' => 0,
            'district_reset' => 0,
            'latlng_sync' => 0,
        ];

        $previewRows = [];

        foreach ($users as $user) {
            $stats['processed']++;

            [$targetCity, $source] = $this->resolveTargetCity(
                userCityId: (int) ($user->city_id ?? 0),
                userProvinceId: (int) ($user->province_id ?? 0),
                cityById: $cityById,
                firstCityByProvince: $firstCityByProvince,
                defaultCity: $defaultCity
            );

            if (! $targetCity) {
                $stats['unchanged']++;
                continue;
            }

            $payload = [];

            $targetCityId = (int) ($targetCity->id ?? 0);
            $targetProvinceId = (int) ($targetCity->province_id ?? 0);

            if ((int) ($user->city_id ?? 0) !== $targetCityId) {
                $payload['city_id'] = $targetCityId;
            }

            if ((int) ($user->province_id ?? 0) !== $targetProvinceId) {
                $payload['province_id'] = $targetProvinceId;
                $stats['province_city_sync']++;
            }

            $targetLat = $targetCity->lat;
            $targetLng = $targetCity->lng;

            if (
                $targetLat !== null
                && $this->normalizeCoordinate($user->lat) !== $this->normalizeCoordinate($targetLat)
            ) {
                $payload['lat'] = $targetLat;
                $stats['latlng_sync']++;
            }

            if (
                $targetLng !== null
                && $this->normalizeCoordinate($user->lng) !== $this->normalizeCoordinate($targetLng)
            ) {
                $payload['lng'] = $targetLng;
                $stats['latlng_sync']++;
            }

            $currentDistrictId = (int) ($user->district_id ?? 0);
            if ($currentDistrictId > 0) {
                $districtCityId = (int) ($districtCityById->get($currentDistrictId, 0));
                if ($districtCityId !== $targetCityId) {
                    $payload['district_id'] = null;
                    $stats['district_reset']++;
                }
            }

            if ($source === 'fallback') {
                $stats['fallback_city']++;
            }

            if ($payload === []) {
                $stats['unchanged']++;
                continue;
            }

            if (! $dryRun) {
                DB::table('users')
                    ->where('id', (int) $user->id)
                    ->update($payload + ['updated_at' => now()]);
            }

            $stats['updated']++;

            if (count($previewRows) < 12) {
                $previewRows[] = [
                    'id' => (int) $user->id,
                    'email' => (string) ($user->email ?? ''),
                    'role' => (string) ($user->role ?? ''),
                    'target_city_id' => $targetCityId,
                    'target_province_id' => $targetProvinceId,
                    'source' => $source,
                ];
            }
        }

        $this->newLine();
        $this->info('Ringkasan Backfill Lokasi');
        $this->line('User diproses: ' . number_format($stats['processed']));
        $this->line('User diupdate: ' . number_format($stats['updated']));
        $this->line('User tidak berubah: ' . number_format($stats['unchanged']));
        $this->line('Menggunakan fallback kota: ' . number_format($stats['fallback_city']));
        $this->line('Sinkron province-city: ' . number_format($stats['province_city_sync']));
        $this->line('Reset district tidak valid: ' . number_format($stats['district_reset']));
        $this->line('Sinkron lat/lng dari kota: ' . number_format($stats['latlng_sync']));

        if (! empty($previewRows)) {
            $this->newLine();
            $this->table(
                ['ID', 'Email', 'Role', 'Province', 'City', 'Sumber'],
                array_map(function (array $row) {
                    return [
                        $row['id'],
                        $row['email'],
                        $row['role'],
                        $row['target_province_id'],
                        $row['target_city_id'],
                        $row['source'],
                    ];
                }, $previewRows)
            );
        }

        if ($dryRun) {
            $this->comment('Dry-run selesai. Jalankan tanpa --dry-run untuk menerapkan update.');
        } else {
            $this->info('Backfill lokasi berhasil diterapkan.');
        }

        return self::SUCCESS;
    }

    private function resolveTargetCity(
        int $userCityId,
        int $userProvinceId,
        $cityById,
        $firstCityByProvince,
        ?object $defaultCity
    ): array {
        if ($userCityId > 0 && $cityById->has($userCityId)) {
            return [$cityById->get($userCityId), 'city_existing'];
        }

        if ($userProvinceId > 0 && $firstCityByProvince->has($userProvinceId)) {
            return [$firstCityByProvince->get($userProvinceId), 'city_by_province'];
        }

        if ($defaultCity) {
            return [$defaultCity, 'fallback'];
        }

        return [null, 'none'];
    }

    private function normalizeCoordinate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 7, '.', '');
    }
}

