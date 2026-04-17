<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminModulesNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_all_module_placeholder_pages(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $routes = [
            'admin.dashboard',
            'admin.modeRequests.index',
            'admin.modules.procurement',
            'admin.modules.marketplace',
            'admin.modules.users',
            'admin.modules.orders',
            'admin.modules.finance',
            'admin.modules.weather',
            'admin.modules.recommendationRules',
            'admin.modules.warehouse',
            'admin.modules.reports',
        ];

        foreach ($routes as $routeName) {
            $this->actingAs($admin)
                ->get(route($routeName))
                ->assertOk();
        }
    }
}
