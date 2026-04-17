<?php

namespace App\Console\Commands;

use App\Services\Weather\WeatherService;
use Illuminate\Console\Command;

class WeatherRefreshCommand extends Command
{
    protected $signature = 'weather:refresh 
        {--type=custom : location_type yang mau di-refresh (custom/city/warehouse/farm)}
        {--id=1 : location_id target}
        {--lat= : latitude}
        {--lng= : longitude}';

    protected $description = 'Refresh weather cache (current & forecast) into DB';

    public function handle(WeatherService $svc): int
    {
        $type = (string) $this->option('type');
        $id   = (int) $this->option('id');
        $lat  = $this->option('lat');
        $lng  = $this->option('lng');

        if ($lat === null || $lng === null) {
            $this->error('Wajib isi --lat dan --lng (contoh: --lat=-6.2 --lng=106.8167)');
            return self::FAILURE;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        $this->info("Refreshing weather cache for {$type}#{$id} ({$lat},{$lng}) ...");

        $svc->current($type, $id, $lat, $lng);
        $svc->forecast($type, $id, $lat, $lng);

        $this->info('Done. Cache updated.');
        return self::SUCCESS;
    }
}
