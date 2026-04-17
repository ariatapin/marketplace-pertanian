<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleModeAccessApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumer_with_approved_affiliate_mode_can_access_affiliate_api(): void
    {
        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        $this->seedConsumerProfile($consumer->id, 'affiliate', 'approved');

        Sanctum::actingAs($consumer);

        $response = $this->getJson('/api/affiliate/dashboard');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'errors' => null,
            ]);
    }

    public function test_consumer_with_approved_seller_mode_can_access_seller_api(): void
    {
        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        $this->seedConsumerProfile($consumer->id, 'farmer_seller', 'approved');

        Sanctum::actingAs($consumer);

        $response = $this->getJson('/api/seller/orders');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'errors' => null,
            ]);
    }

    public function test_consumer_without_approved_mode_is_blocked_from_affiliate_and_seller_api(): void
    {
        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        Sanctum::actingAs($consumer);

        $this->getJson('/api/affiliate/dashboard')
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'data' => null,
                'errors' => null,
            ]);

        $this->getJson('/api/seller/orders')
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'data' => null,
                'errors' => null,
            ]);
    }

    private function seedConsumerProfile(int $userId, string $mode, string $modeStatus): void
    {
        DB::table('consumer_profiles')->insert([
            'user_id' => $userId,
            'address' => null,
            'mode' => $mode,
            'mode_status' => $modeStatus,
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

