<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestMarketplaceAuthRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_accessing_cart_is_redirected_to_landing_login_popup(): void
    {
        $response = $this->get(route('cart.index'));

        $response->assertRedirect(route('landing', ['auth' => 'login']));
    }

    public function test_landing_query_auth_register_opens_register_popup_state(): void
    {
        $response = $this->get(route('landing', ['auth' => 'register']));

        $response->assertOk();
        $response->assertSee("authModalOpen: true", false);
        $response->assertSee("authMode: 'register'", false);
    }
}
