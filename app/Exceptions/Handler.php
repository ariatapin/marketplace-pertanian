<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($this->isApiRequest($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi login berakhir. Silakan login ulang.',
                    'data' => null,
                    'errors' => null,
                ], 419);
            }

            if ($request->hasSession()) {
                $request->session()->regenerateToken();
            }

            $authForm = (string) $request->input('auth_form', 'login');
            if (! in_array($authForm, ['login', 'register'], true)) {
                $authForm = 'login';
            }

            $emailInput = (string) $request->input('email', '');
            $redirect = route('landing', ['auth' => $authForm]);

            $isMarketplaceAuthModal = $request->filled('auth_form');

            if (($request->routeIs('login') || $request->is('login')) && ! $isMarketplaceAuthModal) {
                $redirect = route('login');
            } elseif (($request->routeIs('register') || $request->is('register')) && ! $isMarketplaceAuthModal) {
                $redirect = route('register');
            }

            return redirect()
                ->to($redirect)
                ->withErrors(['email' => 'Sesi login berakhir. Silakan login ulang.'])
                ->withInput([
                    'auth_form' => $authForm,
                    'email' => $emailInput,
                ]);
        });

        $this->renderable(function (Throwable $e, Request $request) {
            if (! $this->isApiRequest($request)) {
                return null;
            }

            if (! $e instanceof HttpExceptionInterface) {
                return null;
            }

            $status = $e->getStatusCode();
            $message = $e->getMessage() !== '' ? $e->getMessage() : 'Request gagal diproses.';

            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => null,
                'errors' => null,
            ], $status);
        });
    }

    protected function invalidJson($request, ValidationException $exception)
    {
        return response()->json([
            'success' => false,
            'message' => 'Validasi gagal.',
            'data' => null,
            'errors' => $exception->errors(),
        ], $exception->status);
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($this->isApiRequest($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
                'errors' => null,
            ], 401);
        }

        return parent::unauthenticated($request, $exception);
    }

    private function isApiRequest(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }
}
