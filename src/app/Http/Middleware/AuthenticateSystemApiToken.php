<?php

namespace App\Http\Middleware;

use App\Models\SystemApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSystemApiToken
{
    /**
     * Route to ability mapping for API endpoints
     */
    private const ROUTE_ABILITIES = [
        // Submissions - read
        'GET:/api/v1/submissions' => 'submissions:read',
        'GET:/api/v1/submissions/approved' => 'submissions:read',
        'GET:/api/v1/submissions/*' => 'submissions:read',
        'GET:/api/v1/forms' => 'forms:read',

        // Submissions - write (import)
        'POST:/api/v1/submissions/import' => 'submissions:import',
        'POST:/api/v1/submissions/import/batch' => 'submissions:import',
    ];

    public function handle(Request $request, Closure $next, ?string $requiredAbility = null): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $systemToken = SystemApiToken::findToken($token);

        if (!$systemToken) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        // Check if token is expired
        if ($systemToken->isExpired()) {
            return response()->json(['message' => 'Token expired'], 401);
        }

        // SECURITY: Validate token ability for this route
        $ability = $requiredAbility ?? $this->getRequiredAbility($request);
        if ($ability && !$systemToken->can($ability)) {
            return response()->json([
                'message' => 'Forbidden - token does not have required ability',
                'required_ability' => $ability,
            ], 403);
        }

        // Update last used timestamp (only for valid, non-expired tokens with proper abilities)
        $systemToken->updateLastUsed();

        // Store token in request for later use
        $request->attributes->set('system_api_token', $systemToken);

        return $next($request);
    }

    /**
     * Determine required ability based on request method and path
     */
    private function getRequiredAbility(Request $request): ?string
    {
        $method = $request->method();
        $path = '/' . ltrim($request->path(), '/');

        // Check exact match first
        $key = "{$method}:{$path}";
        if (isset(self::ROUTE_ABILITIES[$key])) {
            return self::ROUTE_ABILITIES[$key];
        }

        // Check wildcard matches
        foreach (self::ROUTE_ABILITIES as $pattern => $ability) {
            if (str_contains($pattern, '*')) {
                // SECURITY: Use preg_quote() first to escape all regex metacharacters,
                // then replace escaped wildcards with safe pattern
                $regex = preg_quote($pattern, '/');
                $regex = str_replace('\*', '[^/]+', $regex);
                if (preg_match("/^{$regex}$/", $key)) {
                    return $ability;
                }
            }
        }

        // Default: require explicit ability or deny
        // If no mapping exists, token must have '*' ability
        return 'api:access';
    }
}
