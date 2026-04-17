<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumer_profile_separates_seller_p2p_and_mitra_b2b_sections(): void
    {
        $user = User::factory()->create([
            'role' => 'consumer',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
        $response->assertSee('Ajukan Penjual P2P');
        $response->assertDontSee('Program Mitra B2B');
        $response->assertDontSee('Form Mitra Pengadaan Admin');
        $response->assertDontSee('Pengajuan Mitra B2B hanya bisa diakses melalui klik banner/promo mitra');
    }

    public function test_consumer_can_open_mitra_b2b_form_via_program_route(): void
    {
        $user = User::factory()->create([
            'role' => 'consumer',
        ]);

        DB::table('feature_flags')->insert([
            'key' => 'accept_mitra',
            'is_enabled' => true,
            'description' => 'Pengajuan mitra dibuka.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $entryUrl = URL::temporarySignedRoute('program.mitra.entry', now()->addMinutes(5));
        $this->actingAs($user)->get($entryUrl)->assertRedirect(route('program.mitra.form'));

        $response = $this
            ->actingAs($user)
            ->get(route('program.mitra.form'));

        $response->assertOk();
        $response->assertSee('Form Mitra Pengadaan Admin');
    }

    public function test_consumer_cannot_open_mitra_program_directly_without_banner_access(): void
    {
        $user = User::factory()->create([
            'role' => 'consumer',
        ]);

        DB::table('feature_flags')->insert([
            'key' => 'accept_mitra',
            'is_enabled' => true,
            'description' => 'Pengajuan mitra dibuka.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('program.mitra.form'));

        $response->assertRedirect(route('landing'));
    }

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
