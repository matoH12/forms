<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = 'sk'; // Default locale

        // Check if user is logged in and has language preference
        if ($request->user()) {
            $userLocale = $request->user()->getSetting('language');
            if ($userLocale && in_array($userLocale, ['sk', 'en'])) {
                $locale = $userLocale;
            }
        }

        // Set the application locale
        app()->setLocale($locale);

        return $next($request);
    }
}
