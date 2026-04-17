<?php

namespace Tests\Feature\Auth;

use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $provinceId = DB::table('provinces')->insertGetId([
            'name' => 'Jawa Barat',
            'code' => 'JB',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId,
            'name' => 'Bandung',
            'type' => 'Kota',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone_number' => '081234567890',
            'province_id' => $provinceId,
            'city_id' => $cityId,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_new_users_can_register_and_return_to_safe_redirect_target(): void
    {
        $provinceId = DB::table('provinces')->insertGetId([
            'name' => 'Jawa Tengah',
            'code' => 'JT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId,
            'name' => 'Semarang',
            'type' => 'Kota',
            'lat' => -6.9667,
            'lng' => 110.4167,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $redirectTarget = '/produk/store/11?ref=11.' . str_repeat('b', 24);

        $response = $this->post('/register', [
            'name' => 'Redirect User',
            'email' => 'redirect-register@example.com',
            'phone_number' => '081234000111',
            'province_id' => $provinceId,
            'city_id' => $cityId,
            'password' => 'password',
            'password_confirmation' => 'password',
            'redirect_to' => $redirectTarget,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect($redirectTarget);
    }

    public function test_registration_requires_phone_number(): void
    {
        $provinceId = DB::table('provinces')->insertGetId([
            'name' => 'Jawa Barat',
            'code' => 'JB',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId,
            'name' => 'Bandung',
            'type' => 'Kota',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->from('/register')->post('/register', [
            'name' => 'Test User',
            'email' => 'test-no-phone@example.com',
            'province_id' => $provinceId,
            'city_id' => $cityId,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('phone_number');
        $this->assertGuest();
    }
}
