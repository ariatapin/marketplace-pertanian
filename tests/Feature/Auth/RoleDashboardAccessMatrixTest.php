<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleDashboardAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_generic_dashboard_redirect_follows_role_matrix(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        $buyer = User::factory()->create(['role' => 'consumer']);
        $affiliateMode = User::factory()->create(['role' => 'consumer']);
        $sellerMode = User::factory()->create(['role' => 'consumer']);
        $legacyAffiliate = User::factory()->create(['role' => 'affiliate']);
        $legacySeller = User::factory()->create(['role' => 'farmer_seller']);

        $this->seedConsumerProfile($affiliateMode->id, 'affiliate', 'approved');
        $this->seedConsumerProfile($sellerMode->id, 'farmer_seller', 'approved');

        $this->actingAs($admin)->get('/dashboard')->assertRedirect('/admin/dashboard');
        $this->actingAs($mitra)->get('/dashboard')->assertRedirect('/mitra/dashboard');
        $this->actingAs($buyer)->get('/dashboard')->assertRedirect(route('landing'));
        $this->actingAs($affiliateMode)->get('/dashboard')->assertRedirect('/affiliate/dashboard');
        $this->actingAs($sellerMode)->get('/dashboard')->assertRedirect('/seller/dashboard');
        $this->actingAs($legacyAffiliate)->get('/dashboard')->assertRedirect(route('landing'));
        $this->actingAs($legacySeller)->get('/dashboard')->assertRedirect('/seller/dashboard');
    }

    public function test_direct_dashboard_routes_are_guarded_by_mode_and_role(): void
    {
        $buyer = User::factory()->create(['role' => 'consumer']);
        $affiliateMode = User::factory()->create(['role' => 'consumer']);
        $sellerMode = User::factory()->create(['role' => 'consumer']);
        $legacyAffiliate = User::factory()->create(['role' => 'affiliate']);
        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        $this->seedConsumerProfile($affiliateMode->id, 'affiliate', 'approved');
        $this->seedConsumerProfile($sellerMode->id, 'farmer_seller', 'approved');

        $this->actingAs($buyer)->get('/affiliate/dashboard')->assertForbidden();
        $this->actingAs($buyer)->get('/affiliate/dipasarkan')->assertForbidden();
        $this->actingAs($buyer)->get('/affiliate/performa')->assertForbidden();
        $this->actingAs($buyer)->get('/affiliate/dompet')->assertForbidden();
        $this->actingAs($buyer)->get('/seller/dashboard')->assertForbidden();
        $this->actingAs($legacyAffiliate)->get('/affiliate/dashboard')->assertForbidden();
        $this->actingAs($legacyAffiliate)->get('/affiliate/dipasarkan')->assertForbidden();
        $this->actingAs($admin)->get('/affiliate/dashboard')->assertForbidden();
        $this->actingAs($admin)->get('/affiliate/performa')->assertForbidden();
        $this->actingAs($mitra)->get('/seller/dashboard')->assertForbidden();

        $this->actingAs($affiliateMode)->get('/affiliate/dashboard')->assertOk();
        $this->actingAs($affiliateMode)->get('/affiliate/dipasarkan')->assertOk();
        $this->actingAs($affiliateMode)->get('/affiliate/performa')->assertOk();
        $this->actingAs($affiliateMode)->get('/affiliate/dompet')->assertOk();
        $this->actingAs($affiliateMode)->get('/seller/dashboard')->assertForbidden();
        $this->actingAs($sellerMode)->get('/seller/dashboard')->assertOk();
        $this->actingAs($sellerMode)->get('/affiliate/dashboard')->assertForbidden();
    }

    public function test_admin_role_with_whitespace_still_redirects_to_admin_dashboard(): void
    {
        $admin = User::factory()->create(['role' => ' Admin ']);

        $this->actingAs($admin)->get('/dashboard')->assertRedirect('/admin/dashboard');
    }

    public function test_affiliate_gate_accepts_consumer_role_with_whitespace_when_mode_is_approved(): void
    {
        $affiliateMode = User::factory()->create(['role' => ' Consumer ']);
        $this->seedConsumerProfile($affiliateMode->id, 'affiliate', 'approved');

        $this->actingAs($affiliateMode)->get('/affiliate/dashboard')->assertOk();
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
