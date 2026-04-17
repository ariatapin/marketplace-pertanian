<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\RoleAccessService;
use App\Support\RoleRedirector;
use Tests\TestCase;

class RoleRedirectorTest extends TestCase
{
    private function makeRedirector(bool $affiliateAccess = false, bool $sellerAccess = false): RoleRedirector
    {
        $roleAccess = $this->createMock(RoleAccessService::class);
        $roleAccess->method('canAccessAffiliate')->willReturn($affiliateAccess);
        $roleAccess->method('canAccessSeller')->willReturn($sellerAccess);

        return new RoleRedirector($roleAccess);
    }

    public function test_it_returns_admin_dashboard_for_admin_role(): void
    {
        $user = new User(['role' => 'admin']);
        $redirector = $this->makeRedirector();

        $path = $redirector->pathFor($user);

        $this->assertSame('/admin/dashboard', $path);
    }

    public function test_it_returns_admin_dashboard_for_normalized_admin_role(): void
    {
        $user = new User(['role' => ' Admin ']);
        $redirector = $this->makeRedirector();

        $path = $redirector->pathFor($user);

        $this->assertSame('/admin/dashboard', $path);
    }

    public function test_it_returns_mitra_dashboard_for_mitra_role(): void
    {
        $user = new User(['role' => 'mitra']);
        $redirector = $this->makeRedirector();

        $path = $redirector->pathFor($user);

        $this->assertSame('/mitra/dashboard', $path);
    }

    public function test_it_returns_affiliate_dashboard_when_affiliate_access_is_granted(): void
    {
        $user = new User(['role' => 'consumer']);
        $redirector = $this->makeRedirector(affiliateAccess: true);

        $path = $redirector->pathFor($user);

        $this->assertSame('/affiliate/dashboard', $path);
    }

    public function test_it_returns_seller_dashboard_when_seller_access_is_granted(): void
    {
        $user = new User(['role' => 'consumer']);
        $redirector = $this->makeRedirector(affiliateAccess: false, sellerAccess: true);

        $path = $redirector->pathFor($user);

        $this->assertSame('/seller/dashboard', $path);
    }

    public function test_it_returns_consumer_dashboard_for_regular_consumer(): void
    {
        $user = new User(['role' => 'consumer']);
        $redirector = $this->makeRedirector();

        $path = $redirector->pathFor($user);

        $this->assertSame('/dashboard', $path);
    }

    public function test_post_login_redirect_keeps_affiliate_mode_user_on_landing(): void
    {
        $user = new User(['role' => 'consumer']);
        $redirector = $this->makeRedirector(affiliateAccess: true);

        $path = $redirector->postLoginPathFor($user);

        $this->assertSame('/', $path);
    }

    public function test_post_login_redirect_keeps_seller_mode_user_on_landing(): void
    {
        $user = new User(['role' => 'consumer']);
        $redirector = $this->makeRedirector(affiliateAccess: false, sellerAccess: true);

        $path = $redirector->postLoginPathFor($user);

        $this->assertSame('/', $path);
    }
}
