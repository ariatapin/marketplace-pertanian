<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.google.client_id', 'test-google-client-id');
        Config::set('services.google.client_secret', 'test-google-client-secret');
    }

    public function test_google_callback_creates_a_new_user_and_logs_in(): void
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-user-1');
        $socialiteUser->shouldReceive('getEmail')->andReturn('google1@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Google One');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://example.com/a.jpg');

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirectUrl')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get(route('auth.google.callback'));

        $this->assertAuthenticated();
        $response->assertRedirect('/dashboard');
        $this->assertDatabaseHas('users', [
            'email' => 'google1@example.com',
            'google_id' => 'google-user-1',
            'role' => 'consumer',
        ]);
    }

    public function test_google_callback_links_existing_user_by_email(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'google2@example.com',
            'role' => 'consumer',
            'google_id' => null,
        ]);

        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-user-2');
        $socialiteUser->shouldReceive('getEmail')->andReturn('google2@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Google Two');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://example.com/b.jpg');

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirectUrl')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get(route('auth.google.callback'));

        $this->assertAuthenticatedAs($existingUser->fresh());
        $response->assertRedirect('/dashboard');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'email' => 'google2@example.com',
            'google_id' => 'google-user-2',
        ]);
    }

    public function test_google_callback_respects_safe_redirect_target_in_session(): void
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-user-3');
        $socialiteUser->shouldReceive('getEmail')->andReturn('google3@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Google Three');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://example.com/c.jpg');

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirectUrl')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $redirectTarget = '/produk/store/12?ref=12.' . str_repeat('c', 24);
        $response = $this->withSession([
            'auth.redirect_to' => $redirectTarget,
        ])->get(route('auth.google.callback'));

        $this->assertAuthenticated();
        $response->assertRedirect($redirectTarget);
    }
}
