<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken as BaseValidateCsrfToken;
use Symfony\Component\HttpFoundation\Response;

class ValidateCsrfToken extends BaseValidateCsrfToken
{
    public function handle($request, Closure $next): Response
    {
        // Skip CSRF for unauthenticated requests to auth-protected routes (admin, user).
        // Without this, CSRF runs before auth middleware and returns 419 instead of 302.
        // This is safe because auth middleware will reject these requests anyway.
        if (!$request->user() && $request->is('admin/*', 'admin', 'my/*', 'profile/*')) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
