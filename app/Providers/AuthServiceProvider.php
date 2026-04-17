<?php

namespace App\Providers;

use App\Models\StoreProduct;
use App\Models\User;
use App\Policies\StoreProductPolicy;
use App\Services\RoleAccessService;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        StoreProduct::class => StoreProductPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::define('access-admin', fn (User $user) => strtolower(trim((string) $user->role)) === 'admin');
        Gate::define('access-mitra', fn (User $user) => strtolower(trim((string) $user->role)) === 'mitra');
        Gate::define('access-consumer', fn (User $user) => strtolower(trim((string) $user->role)) === 'consumer');
        Gate::define('access-affiliate-api', fn (User $user): bool => app(RoleAccessService::class)->canAccessAffiliate($user));
        Gate::define('access-seller-api', fn (User $user): bool => app(RoleAccessService::class)->canAccessSeller($user));

        Gate::define('access-consumer-active-dashboard', function (User $user): bool {
            if (strtolower(trim((string) $user->role)) !== 'consumer') {
                return false;
            }

            if (! DB::getSchemaBuilder()->hasTable('consumer_profiles')) {
                return false;
            }

            $profile = DB::table('consumer_profiles')
                ->where('user_id', $user->id)
                ->first();

            if (! $profile) {
                return false;
            }

            return in_array($profile->mode, ['affiliate', 'farmer_seller'], true)
                && $profile->mode_status === 'approved';
        });

        Gate::define('order-belongs-to-buyer', fn (User $user, object $order): bool => (int) $order->buyer_id === (int) $user->id);
        Gate::define('order-belongs-to-seller', fn (User $user, object $order): bool => (int) $order->seller_id === (int) $user->id);
    }
}
