<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WithdrawRouteConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_web_withdraw_routes_reject_non_numeric_withdraw_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post('/admin/withdraws/abc/approve')
            ->assertNotFound();

        $this->actingAs($admin)
            ->post('/admin/withdraws/abc/paid', [
                'transfer_reference' => 'TRX-INVALID-PATH',
            ])
            ->assertNotFound();

        $this->actingAs($admin)
            ->post('/admin/withdraws/abc/reject')
            ->assertNotFound();
    }

    public function test_admin_api_withdraw_routes_reject_non_numeric_withdraw_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/withdraws/abc/approve')
            ->assertNotFound();

        $this->postJson('/api/admin/withdraws/abc/paid', [
            'transfer_reference' => 'TRX-INVALID-PATH',
        ])->assertNotFound();

        $this->postJson('/api/admin/withdraws/abc/reject')
            ->assertNotFound();
    }
}

