<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        // Signed-in users who open /login or /register go to their own
        // role's dashboard (the framework default is /dashboard for everyone).
        $middleware->redirectUsersTo(
            fn (Request $request) => $request->user()?->homePath() ?? '/',
        );

        // The simulated gateway webhook authenticates via HMAC signature, not a
        // session CSRF token, so it is excluded from CSRF verification.
        $middleware->validateCsrfTokens(except: [
            'sandbox/payments/*/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
