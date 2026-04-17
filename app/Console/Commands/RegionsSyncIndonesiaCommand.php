<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RegionsSyncIndonesiaCommand extends Command
{
    protected $signature = 'regions:sync-indonesia
        {--dry-run : Simulasi sinkronisasi tanpa update database}
        {--without-districts : Lewati sinkronisasi kecamatan}
        {--timeout=25 : Timeout request API (detik)}
        {--sleep-ms=40 : Delay antar request API (ms)}
        {--base-url=https://www.emsifa.com/api-wilayah-indonesia/api : Endpoint base API wilayah Indonesia}';

    protected $description = 'Sinkronisasi master wilayah Indonesia (provinsi, kota/kabupaten, kecamatan).';

    private const PROVINCE_CODES = [
        11 => 'ID-AC',
        12 => 'ID-SU',
        13 => 'ID-SB',
        14 => 'ID-RI',
        15 => 'ID-JA',
        16 => 'ID-SS',
        17 => 'ID-BE',
        18 => 'ID-LA',
        19 => 'ID-BB',
        21 => 'ID-KR',
        31 => 'ID-JK',
        32 => 'ID-JB',
        33 => 'ID-JT',
        34 => 'ID-YO',
        35 => 'ID-JI',
        36 => 'ID-BT',
        51 => 'ID-BA',
        52 => 'ID-NB',
        53 => 'ID-NT',
        61 => 'ID-KB',
        62 => 'ID-KT',
        63 => 'ID-KS',
        64 => 'ID-KI',
        65 => 'ID-KU',
        71 => 'ID-SA',
        72 => 'ID-ST',
        73 => 'ID-SN',
        74 => 'ID-SG',
        75 => 'ID-GO',
        76 => 'ID-SR',
        81 => 'ID-MA',
        82 => 'ID-MU',
        91 => 'ID-PB',
        92 => 'ID-PA',
        93 => 'ID-PS',
        94 => 'ID-PT',
        95 => 'ID-PP',
        96 => 'ID-PD',
    ];

    private const PROVINCE_NAMES = [
        11 => 'Aceh',
        12 => 'Sumatera Utara',
        13 => 'Sumatera Barat',
        14 => 'Riau',
        15 => 'Jambi',
        16 => 'Sumatera Selatan',
        17 => 'Bengkulu',
        18 => 'Lampung',
        19 => 'Kepulauan Bangka Belitung',
        21 => 'Kepulauan Riau',
        31 => 'DKI Jakarta',
        32 => 'Jawa Barat',
        33 => 'Jawa Tengah',
        34 => 'DI Yogyakarta',
        35 => 'Jawa Timur',
        36 => 'Banten',
        51 => 'Bali',
        52 => 'Nusa Tenggara Barat',
        53 => 'Nusa Tenggara Timur',
        61 => 'Kalimantan Barat',
        62 => 'Kalimantan Tengah',
        63 => 'Kalimantan Selatan',
        64 => 'Kalimantan Timur',
        65 => 'Kalimantan Utara',
        71 => 'Sulawesi Utara',
        72 => 'Sulawesi Tengah',
        73 => 'Sulawesi Selatan',
        74 => 'Sulawesi Tenggara',
        75 => 'Gorontalo',
        76 => 'Sulawesi Barat',
        81 => 'Maluku',
        82 => 'Maluku Utara',
        91 => 'Papua Barat',
        92 => 'Papua',
        93 => 'Papua Selatan',
        94 => 'Papua Tengah',
        95 => 'Papua Pegunungan',
        96 => 'Papua Barat Daya',
    ];

    private const FALLBACK_CITIES = [
        ['id' => 9371, 'province_id' => 93, 'type' => 'Kabupaten', 'name' => 'Merauke'],
        ['id' => 9471, 'province_id' => 94, 'type' => 'Kabupaten', 'name' => 'Nabire'],
        ['id' => 9571, 'province_id' => 95, 'type' => 'Kabupaten', 'name' => 'Wamena'],
        ['id' => 9671, 'province_id' => 96, 'type' => 'Kota', 'name' => 'Sorong'],
    ];

    private const FALLBACK_DISTRICTS = [
        ['id' => 9371010, 'city_id' => 9371, 'name' => 'Merauke'],
        ['id' => 9471010, 'city_id' => 9471, 'name' => 'Nabire'],
        ['id' => 9571010, 'city_id' => 9571, 'name' => 'Wamena'],
        ['id' => 9671010, 'city_id' => 9671, 'name' => 'Sorong Barat'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $syncDistricts = ! (bool) $this->option('without-districts');
        $timeout = max(5, (int) $this->option('timeout'));
        $sleepMs = max(0, (int) $this->option('sleep-ms'));
        $baseUrl = rtrim((string) $this->option('base-url'), '/');

        $this->info('Memulai sinkronisasi master wilayah Indonesia...');
        $this->line('Mode: ' . ($dryRun ? 'DRY RUN' : 'UPDATE DB'));
        $this->line('District sync: ' . ($syncDistricts ? 'YA' : 'TIDAK'));
        $this->line("Endpoint: {$baseUrl}");

        $client = Http::acceptJson()->timeout($timeout)->retry(2, 700);

        try {
            $provincePayload = $this->fetchJson($client, "{$baseUrl}/provinces.json");
        } catch (\Throwable $e) {
            $this->error('Gagal mengambil daftar provinsi: ' . $e->getMessage());
            return self::FAILURE;
        }

        $now = now();

        $provinceRows = collect($provincePayload)
            ->map(function ($row) use ($now) {
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0) {
                    return null;
                }

                return [
                    'id' => $id,
                    'name' => trim((string) ($row['name'] ?? '')),
                    'code' => self::PROVINCE_CODES[$id] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->filter(fn ($row) => is_array($row) && $row['name'] !== '')
            ->keyBy('id');

        foreach (self::PROVINCE_NAMES as $provinceId => $provinceName) {
            if (! $provinceRows->has($provinceId)) {
                $provinceRows->put($provinceId, [
                    'id' => $provinceId,
                    'name' => $provinceName,
                    'code' => self::PROVINCE_CODES[$provinceId] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $provinceRows = $provinceRows
            ->sortKeys()
            ->values();

        if ($provinceRows->isEmpty()) {
            $this->error('Payload provinsi kosong/tidak valid.');
            return self::FAILURE;
        }

        if (! $dryRun) {
            $this->upsertChunked('provinces', $provinceRows->all(), ['id'], ['name', 'code', 'updated_at']);
        }
        $this->info('Provinsi siap: ' . number_format($provinceRows->count()));

        $existingCityCoords = DB::table('cities')
            ->select('id', 'lat', 'lng')
            ->get()
            ->keyBy(fn ($row) => (int) $row->id);

        $cityRows = [];
        foreach ($provinceRows as $province) {
            try {
                $cityPayload = $this->fetchJson($client, "{$baseUrl}/regencies/{$province['id']}.json");
            } catch (\Throwable $e) {
                $this->warn("Lewati provinsi {$province['name']} (gagal ambil kota): {$e->getMessage()}");
                continue;
            }

            foreach ((array) $cityPayload as $row) {
                $cityId = (int) ($row['id'] ?? 0);
                if ($cityId <= 0) {
                    continue;
                }

                [$cityType, $cityName] = $this->normalizeCityName((string) ($row['name'] ?? ''));
                if ($cityName === '') {
                    continue;
                }

                $existing = $existingCityCoords->get($cityId);

                $cityRows[] = [
                    'id' => $cityId,
                    'province_id' => (int) $province['id'],
                    'type' => $cityType,
                    'name' => $cityName,
                    'lat' => $existing?->lat,
                    'lng' => $existing?->lng,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        if (empty($cityRows)) {
            $this->error('Tidak ada data kota/kabupaten yang berhasil diambil.');
            return self::FAILURE;
        }

        $cityRowsById = collect($cityRows)->keyBy('id');
        foreach (self::FALLBACK_CITIES as $city) {
            if ($cityRowsById->has($city['id'])) {
                continue;
            }

            $existing = $existingCityCoords->get((int) $city['id']);
            $cityRowsById->put($city['id'], [
                'id' => (int) $city['id'],
                'province_id' => (int) $city['province_id'],
                'type' => (string) $city['type'],
                'name' => (string) $city['name'],
                'lat' => $existing?->lat,
                'lng' => $existing?->lng,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $cityRows = $cityRowsById->values()->all();

        if (! $dryRun) {
            $this->upsertChunked('cities', $cityRows, ['id'], ['province_id', 'type', 'name', 'updated_at']);
        }
        $this->info('Kota/Kabupaten siap: ' . number_format(count($cityRows)));

        $districtCount = 0;
        if ($syncDistricts) {
            $existingDistrictCoords = DB::table('districts')
                ->select('id', 'lat', 'lng')
                ->get()
                ->keyBy(fn ($row) => (int) $row->id);

            $districtBuffer = [];
            foreach ($cityRows as $city) {
                try {
                    $districtPayload = $this->fetchJson($client, "{$baseUrl}/districts/{$city['id']}.json");
                } catch (\Throwable $e) {
                    $this->warn("Lewati city_id {$city['id']} (gagal ambil kecamatan): {$e->getMessage()}");
                    continue;
                }

                foreach ((array) $districtPayload as $row) {
                    $districtId = (int) ($row['id'] ?? 0);
                    if ($districtId <= 0) {
                        continue;
                    }

                    $districtName = trim((string) ($row['name'] ?? ''));
                    if ($districtName === '') {
                        continue;
                    }

                    $existing = $existingDistrictCoords->get($districtId);
                    $districtBuffer[] = [
                        'id' => $districtId,
                        'city_id' => (int) $city['id'],
                        'name' => $districtName,
                        'lat' => $existing?->lat,
                        'lng' => $existing?->lng,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $districtCount++;
                }

                if (count($districtBuffer) >= 1200) {
                    if (! $dryRun) {
                        $this->upsertChunked('districts', $districtBuffer, ['id'], ['city_id', 'name', 'updated_at']);
                    }
                    $districtBuffer = [];
                }

                if ($sleepMs > 0) {
                    usleep($sleepMs * 1000);
                }
            }

            if (! empty($districtBuffer) && ! $dryRun) {
                $this->upsertChunked('districts', $districtBuffer, ['id'], ['city_id', 'name', 'updated_at']);
            }

            $fallbackDistrictRows = [];
            $districtIds = collect($districtBuffer)->pluck('id')->flip();
            foreach (self::FALLBACK_DISTRICTS as $district) {
                $districtId = (int) $district['id'];
                if ($districtIds->has($districtId)) {
                    continue;
                }

                $existing = $existingDistrictCoords->get($districtId);
                $fallbackDistrictRows[] = [
                    'id' => $districtId,
                    'city_id' => (int) $district['city_id'],
                    'name' => (string) $district['name'],
                    'lat' => $existing?->lat,
                    'lng' => $existing?->lng,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (! empty($fallbackDistrictRows)) {
                $districtCount += count($fallbackDistrictRows);
                if (! $dryRun) {
                    $this->upsertChunked('districts', $fallbackDistrictRows, ['id'], ['city_id', 'name', 'updated_at']);
                }
            }
        }

        $this->newLine();
        $this->info('Sinkronisasi selesai.');
        $this->line('Total provinsi: ' . number_format($provinceRows->count()));
        $this->line('Total kota/kabupaten: ' . number_format(count($cityRows)));
        $this->line('Total kecamatan: ' . number_format($districtCount));

        return self::SUCCESS;
    }

    private function fetchJson($client, string $url): array
    {
        $response = $client->get($url);
        if (! $response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()}");
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new \RuntimeException('Payload bukan array JSON.');
        }

        return $payload;
    }

    private function normalizeCityName(string $rawName): array
    {
        $name = trim($rawName);
        if ($name === '') {
            return ['Kota', ''];
        }

        if (Str::startsWith(Str::upper($name), 'KABUPATEN ')) {
            return ['Kabupaten', trim(substr($name, 10))];
        }

        if (Str::startsWith(Str::upper($name), 'KOTA ')) {
            return ['Kota', trim(substr($name, 5))];
        }

        return ['Kota', $name];
    }

    private function upsertChunked(string $table, array $rows, array $uniqueBy, array $updateColumns): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->upsert($chunk, $uniqueBy, $updateColumns);
        }
    }
}
