<?php

namespace Tests\Feature\Consumer;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConsumerModeExclusivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_affiliate_cannot_request_farmer_seller_mode(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);

        DB::table('consumer_profiles')->insert([
            'user_id' => $consumer->id,
            'address' => null,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($consumer)
            ->from(route('profile.edit'))
            ->post(route('profile.requestFarmerSeller'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('mode');

        $this->assertDatabaseHas('consumer_profiles', [
            'user_id' => $consumer->id,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
        ]);
    }

    public function test_active_farmer_seller_cannot_request_affiliate_mode(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);

        DB::table('consumer_profiles')->insert([
            'user_id' => $consumer->id,
            'address' => null,
            'mode' => 'farmer_seller',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($consumer)
            ->from(route('profile.edit'))
            ->post(route('profile.requestAffiliate'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('mode');

        $this->assertDatabaseHas('consumer_profiles', [
            'user_id' => $consumer->id,
            'mode' => 'farmer_seller',
            'mode_status' => 'approved',
            'requested_mode' => null,
        ]);
    }
}
