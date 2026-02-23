<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // SECURITY: Trust only specific proxies, not '*'
        // Configure via TRUSTED_PROXIES env variable (comma-separated IPs or CIDR ranges)
        // Examples: "10.0.0.1,10.0.0.2" or "10.0.0.0/8,172.16.0.0/12"
        $trustedProxies = env('TRUSTED_PROXIES', '');

        if ($trustedProxies === '*') {
            // Explicitly allow '*' only if intentionally set (e.g., behind known LB)
            $middleware->trustProxies(at: '*');
        } elseif (!empty($trustedProxies)) {
            // Use configured proxy IPs/ranges
            $middleware->trustProxies(at: explode(',', $trustedProxies));
        }
        // If empty, don't trust any proxies (secure default)

        // SECURITY: Replace default CSRF with custom one that skips CSRF for
        // unauthenticated requests to admin/user routes (returns 302, not 419).
        $middleware->web(
            remove: [\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class],
            append: [
                \App\Http\Middleware\ValidateCsrfToken::class,
                \App\Http\Middleware\SetLocale::class,
                \App\Http\Middleware\HandleInertiaRequests::class,
            ],
        );

        // Add Sanctum stateful middleware for API routes (enables cookie-based auth)
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'role' => \App\Http\Middleware\CheckRole::class,
            'auth.keycloak' => \App\Http\Middleware\KeycloakAuthenticate::class,
            'system.api.token' => \App\Http\Middleware\AuthenticateSystemApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
