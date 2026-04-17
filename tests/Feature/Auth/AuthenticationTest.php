<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_users_can_authenticate_and_return_to_safe_redirect_target(): void
    {
        $user = User::factory()->create();
        $redirectTarget = '/produk/store/10?ref=10.' . str_repeat('a', 24);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'redirect_to' => $redirectTarget,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect($redirectTarget);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_affiliate_mode_user_is_redirected_to_landing_after_login(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);

        DB::table('consumer_profiles')->insert([
            'user_id' => $user->id,
            'address' => null,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('landing'));
    }

    public function test_seller_mode_user_is_redirected_to_landing_after_login(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);

        DB::table('consumer_profiles')->insert([
            'user_id' => $user->id,
            'address' => null,
            'mode' => 'farmer_seller',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('landing'));
    }

    public function test_admin_user_is_redirected_to_admin_dashboard_even_when_intended_is_landing(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this
            ->withSession(['url.intended' => route('landing')])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/admin/dashboard');
    }

    public function test_authenticated_admin_opening_login_page_is_redirected_to_admin_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/login');

        $response->assertRedirect('/admin/dashboard');
    }

    public function test_authenticated_consumer_can_switch_account_to_admin_without_manual_logout(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this
            ->actingAs($consumer)
            ->post('/login', [
                'email' => $admin->email,
                'password' => 'password',
            ]);

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect('/admin/dashboard');
    }

    public function test_affiliate_mode_user_can_open_affiliate_dashboard_after_login(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);

        DB::table('consumer_profiles')->insert([
            'user_id' => $user->id,
            'address' => null,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $loginResponse = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $loginResponse->assertRedirect(route('landing'));

        $dashboardResponse = $this->get(route('affiliate.dashboard'));
        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('Dashboard Affiliate');
    }
}
