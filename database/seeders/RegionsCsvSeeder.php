<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegionsCsvSeeder extends Seeder
{
    public function run(): void
    {
        $base = database_path('seeders/data');

        $this->seedProvinces($base . '/provinces.csv');
        $this->seedCities($base . '/cities.csv');
        $this->seedDistricts($base . '/districts.csv');
    }

    private function seedProvinces(string $path): void
    {
        $rows = $this->readCsv($path);

        DB::transaction(function () use ($rows) {
            foreach ($rows as $r) {
                DB::table('provinces')->updateOrInsert(
                    ['id' => (int) $r['id']],
                    [
                        'name' => $r['name'],
                        'code' => $r['code'] ?: null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        });
    }

    private function seedCities(string $path): void
    {
        $rows = $this->readCsv($path);

        DB::transaction(function () use ($rows) {
            foreach ($rows as $r) {
                DB::table('cities')->updateOrInsert(
                    ['id' => (int) $r['id']],
                    [
                        'province_id' => (int) $r['province_id'],
                        'type' => $r['type'] ?: 'Kota',
                        'name' => $r['name'],
                        'lat' => $this->toFloatOrNull($r['lat']),
                        'lng' => $this->toFloatOrNull($r['lng']),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        });
    }

    private function seedDistricts(string $path): void
    {
        $rows = $this->readCsv($path);

        DB::transaction(function () use ($rows) {
            foreach ($rows as $r) {
                DB::table('districts')->updateOrInsert(
                    ['id' => (int) $r['id']],
                    [
                        'city_id' => (int) $r['city_id'],
                        'name' => $r['name'],
                        'lat' => $this->toFloatOrNull($r['lat'] ?? null),
                        'lng' => $this->toFloatOrNull($r['lng'] ?? null),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        });
    }

    private function readCsv(string $path): array
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("CSV tidak ditemukan: {$path}");
        }

        $fh = fopen($path, 'r');
        if ($fh === false) {
            throw new \RuntimeException("Gagal membuka CSV: {$path}");
        }

        $header = fgetcsv($fh);
        if (!$header) {
            fclose($fh);
            throw new \RuntimeException("CSV kosong / header tidak valid: {$path}");
        }

        // normalize header
        $header = array_map(fn($h) => trim($h), $header);

        $rows = [];
        while (($line = fgetcsv($fh)) !== false) {
            if (count($line) === 1 && trim((string)$line[0]) === '') {
                continue;
            }
            $line = array_map(fn($v) => is_string($v) ? trim($v) : $v, $line);
            $rows[] = array_combine($header, $line);
        }

        fclose($fh);
        return $rows;
    }

    private function toFloatOrNull($v): ?float
    {
        if ($v === null) return null;
        $v = trim((string) $v);
        if ($v === '') return null;
        return (float) $v;
    }
}
