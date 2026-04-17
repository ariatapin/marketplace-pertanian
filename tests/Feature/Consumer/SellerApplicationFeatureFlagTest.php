<?php

namespace Tests\Feature\Consumer;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SellerApplicationFeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumer_can_request_seller_mode_even_when_mitra_submission_is_closed(): void
    {
        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        DB::table('feature_flags')->insert([
            'key' => 'accept_mitra',
            'is_enabled' => false,
            'description' => 'Pengajuan mitra ditutup sementara.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($consumer)
            ->from(route('profile.edit'))
            ->post(route('profile.requestFarmerSeller'));

        $response->assertRedirect(route('profile.edit'));
        $this->assertDatabaseHas('consumer_profiles', [
            'user_id' => $consumer->id,
            'mode' => 'buyer',
            'requested_mode' => 'farmer_seller',
            'mode_status' => 'pending',
        ]);
    }

    public function test_consumer_can_request_seller_mode_when_mitra_submission_is_open(): void
    {
        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        DB::table('feature_flags')->insert([
            'key' => 'accept_mitra',
            'is_enabled' => true,
            'description' => 'Pengajuan mitra dibuka.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($consumer)
            ->from(route('profile.edit'))
            ->post(route('profile.requestFarmerSeller'));

        $response->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('consumer_profiles', [
            'user_id' => $consumer->id,
            'mode' => 'buyer',
            'mode_status' => 'pending',
            'requested_mode' => 'farmer_seller',
        ]);
    }
}
