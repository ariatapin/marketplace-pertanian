<?php

namespace Tests\Feature\Consumer;

use App\Models\User;
use App\Services\ConsumerModeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConsumerApprovalGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_farmer_seller_user_cannot_access_consumer_dashboard_before_admin_approval(): void
    {
        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        DB::table('consumer_profiles')->insert([
            'user_id' => $consumer->id,
            'address' => null,
            'mode' => 'buyer',
            'mode_status' => 'pending',
            'requested_mode' => 'farmer_seller',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($consumer)->get('/dashboard');

        $response->assertRedirect(route('landing'));
    }

    public function test_farmer_seller_user_is_redirected_to_seller_dashboard_after_admin_approval(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        DB::table('consumer_profiles')->insert([
            'user_id' => $consumer->id,
            'address' => null,
            'mode' => 'buyer',
            'mode_status' => 'pending',
            'requested_mode' => 'farmer_seller',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(ConsumerModeService::class)->approveMode($admin, $consumer->id, 'farmer_seller');

        $response = $this->actingAs($consumer)->get('/dashboard');

        $response->assertRedirect('/seller/dashboard');
    }

    public function test_affiliate_user_is_redirected_to_affiliate_dashboard_after_admin_approval(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        DB::table('consumer_profiles')->insert([
            'user_id' => $consumer->id,
            'address' => null,
            'mode' => 'buyer',
            'mode_status' => 'pending',
            'requested_mode' => 'affiliate',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(ConsumerModeService::class)->approveMode($admin, $consumer->id, 'affiliate');

        $response = $this->actingAs($consumer)->get('/dashboard');

        $response->assertRedirect('/affiliate/dashboard');
    }
}
