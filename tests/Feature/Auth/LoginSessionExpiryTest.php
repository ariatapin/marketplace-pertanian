<?php

namespace Tests\Feature\Auth;

use App\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

class LoginSessionExpiryTest extends TestCase
{
    public function test_marketplace_login_post_with_expired_csrf_redirects_to_landing_login_modal(): void
    {
        $request = $this->makeRequest('/login', 'POST', [
            'auth_form' => 'login',
            'email' => 'admin@example.test',
            'password' => 'secret',
        ]);

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $this->app->make(Handler::class)->render($request, new TokenMismatchException());

        $this->assertSame(route('landing', ['auth' => 'login']), $response->getTargetUrl());
    }

    public function test_standard_login_post_with_expired_csrf_redirects_to_login_page(): void
    {
        $request = $this->makeRequest('/login', 'POST', [
            'email' => 'admin@example.test',
            'password' => 'secret',
        ]);

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $this->app->make(Handler::class)->render($request, new TokenMismatchException());

        $this->assertSame(route('login'), $response->getTargetUrl());
    }

    public function test_api_login_post_with_expired_csrf_returns_json_419_contract(): void
    {
        $request = $this->makeRequest('/login', 'POST', [
            'email' => 'admin@example.test',
            'password' => 'secret',
        ], ['HTTP_ACCEPT' => 'application/json']);

        /** @var \Illuminate\Http\JsonResponse $response */
        $response = $this->app->make(Handler::class)->render($request, new TokenMismatchException());

        $this->assertSame(419, $response->getStatusCode());
        $this->assertSame([
            'success' => false,
            'message' => 'Sesi login berakhir. Silakan login ulang.',
            'data' => null,
            'errors' => null,
        ], $response->getData(true));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $server
     */
    private function makeRequest(string $uri, string $method, array $payload = [], array $server = []): Request
    {
        $request = Request::create($uri, $method, $payload, [], [], $server);
        $session = $this->app['session.store'];
        $session->start();
        $request->setLaravelSession($session);

        return $request;
    }
}
