<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // User must have at least viewer role to access admin panel
        if (!$request->user() || !$request->user()->hasMinRole(User::ROLE_VIEWER)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
            abort(403, 'Prístup zamietnutý');
        }

        return $next($request);
    }
}
