<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $canUseOpenWeather = filled(config('weather.openweather.key')) && ! app()->environment('testing');
        $fallbackLat = (float) config('weather.fallback.lat', 0);
        $fallbackLng = (float) config('weather.fallback.lng', 0);

        if ($canUseOpenWeather) {
            // Warm-up cache baseline untuk fallback lokasi umum.
            $schedule->command(sprintf(
                'weather:refresh --type=custom --id=1 --lat=%s --lng=%s',
                $fallbackLat,
                $fallbackLng
            ))
                ->everyTwoHours()
                ->withoutOverlapping()
                ->runInBackground();

        }

        if (
            (bool) config('recommendation.enabled', true)
            && (bool) config('recommendation.sync.enabled', true)
        ) {
            $schedule->command('recommendations:sync')
                ->cron((string) config('recommendation.sync.cron', '23 * * * *'))
                ->withoutOverlapping()
                ->runInBackground();
        }

        // CATATAN-AUDIT: Role automation cycle dikendalikan flag automation_role_cycle dari panel Admin.
        $schedule->command('automation:role-cycle')
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
