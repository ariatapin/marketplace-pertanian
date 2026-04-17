<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiUserActiveGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocked_user_cannot_access_authenticated_api_routes(): void
    {
        $blocked = User::factory()->create([
            'role' => 'consumer',
            'is_suspended' => true,
            'suspended_at' => now(),
            'suspension_note' => '[BLOCKED] Pelanggaran kebijakan',
        ]);

        Sanctum::actingAs($blocked);

        $response = $this->getJson('/api/consumer/orders');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'data' => null,
            ])
            ->assertJsonPath('errors.suspended', true)
            ->assertJsonPath('errors.blocked', true);
    }

    public function test_suspended_user_cannot_access_authenticated_api_routes(): void
    {
        $suspended = User::factory()->create([
            'role' => 'consumer',
            'is_suspended' => true,
            'suspended_at' => now(),
            'suspension_note' => '[SUSPEND] Menunggu verifikasi',
        ]);

        Sanctum::actingAs($suspended);

        $response = $this->getJson('/api/consumer/orders');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'data' => null,
            ])
            ->assertJsonPath('errors.suspended', true)
            ->assertJsonPath('errors.blocked', false);
    }
}

