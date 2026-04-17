<?php

namespace Tests\Feature\Automation;

use Database\Seeders\AutomationLoadUsersSeeder;
use Database\Seeders\UsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AutomationLoadUsersSeederCompositionTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_generate_exact_target_distribution_without_legacy_demo_users(): void
    {
        config()->set('demo.seed_legacy_users', false);

        $this->seed(UsersSeeder::class);
        $this->seed(AutomationLoadUsersSeeder::class);

        $adminCount = (int) DB::table('users')
            ->whereRaw('LOWER(TRIM(role)) = ?', ['admin'])
            ->count();
        $mitraCount = (int) DB::table('users')
            ->whereRaw('LOWER(TRIM(role)) = ?', ['mitra'])
            ->count();
        $consumerCount = (int) DB::table('users')
            ->whereRaw('LOWER(TRIM(role)) = ?', ['consumer'])
            ->count();
        $nonAdminCount = (int) DB::table('users')
            ->whereRaw('LOWER(TRIM(role)) <> ?', ['admin'])
            ->count();

        $sellerModeApprovedCount = (int) DB::table('consumer_profiles')
            ->where('mode', 'farmer_seller')
            ->where('mode_status', 'approved')
            ->count();
        $affiliateModeApprovedCount = (int) DB::table('consumer_profiles')
            ->where('mode', 'affiliate')
            ->where('mode_status', 'approved')
            ->count();
        $buyerModeCount = (int) DB::table('consumer_profiles')
            ->where('mode', 'buyer')
            ->count();

        $this->assertSame(1, $adminCount);
        $this->assertSame(10, $mitraCount);
        $this->assertSame(90, $consumerCount);
        $this->assertSame(100, $nonAdminCount);
        $this->assertSame(10, $sellerModeApprovedCount);
        $this->assertSame(10, $affiliateModeApprovedCount);
        $this->assertSame(70, $buyerModeCount);
    }

    public function test_users_seeder_can_still_seed_legacy_demo_users_when_flag_enabled(): void
    {
        config()->set('demo.seed_legacy_users', true);

        $this->seed(UsersSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'mitra@demo.test',
            'role' => 'mitra',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'petani.penjual@demo.test',
            'role' => 'consumer',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'petani.affiliate@demo.test',
            'role' => 'consumer',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'petani.consumer@demo.test',
            'role' => 'consumer',
        ]);
    }
}

