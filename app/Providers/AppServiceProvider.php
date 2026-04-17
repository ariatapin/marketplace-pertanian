<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Weather\BmkgWeatherService;
use App\Services\Weather\OpenWeatherClient;
use App\Services\Weather\WeatherAggregatorService;
use App\Services\Weather\WeatherService;
use App\Services\Location\LocationResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('testing')) {
            $this->app->singleton(OpenWeatherClient::class, fn () => new class extends OpenWeatherClient {
                public function __construct()
                {
                    // Skip API key requirement and external calls during automated tests.
                }

                public function current(float $lat, float $lng): array
                {
                    return [
                        'coord' => ['lat' => $lat, 'lon' => $lng],
                        'weather' => [['main' => 'Clear', 'description' => 'clear sky']],
                        'main' => ['temp' => 28],
                    ];
                }

                public function forecast(float $lat, float $lng): array
                {
                    return ['list' => []];
                }
            });
        } else {
            $this->app->singleton(OpenWeatherClient::class, fn () => new OpenWeatherClient());
        }

        $this->app->singleton(BmkgWeatherService::class, fn () => new BmkgWeatherService());
        $this->app->singleton(WeatherAggregatorService::class, fn ($app) => new WeatherAggregatorService(
            $app->make(OpenWeatherClient::class),
            $app->make(BmkgWeatherService::class)
        ));
        $this->app->singleton(WeatherService::class, fn ($app) => new WeatherService(
            $app->make(WeatherAggregatorService::class)
        ));
        $this->app->singleton(LocationResolver::class, fn() => new LocationResolver());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('components.admin-layout', function ($view): void {
            $pendingProcurementNotificationCount = 0;
            $user = Auth::user();

            if (
                $user
                && strtolower(trim((string) ($user->role ?? ''))) === 'admin'
                && Schema::hasTable('admin_orders')
            ) {
                $pendingProcurementNotificationCount = (int) DB::table('admin_orders')
                    ->where('status', 'pending')
                    ->count();
            }

            $view->with('pendingProcurementNotificationCount', $pendingProcurementNotificationCount);
        });
    }
}
