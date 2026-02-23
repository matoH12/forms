<?php

/**
 * CORS Configuration
 * SECURITY: Never use '*' with supports_credentials = true
 */
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Only POST is needed from browser - PUT/DELETE use method spoofing for WAF compatibility
    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    // SECURITY: Use APP_URL or explicit list, never '*' with credentials
    'allowed_origins' => array_filter([
        env('APP_URL'),
        env('CORS_ALLOWED_ORIGIN'),  // Optional additional origin
    ]),

    // For dynamic subdomains (e.g., *.example.com)
    'allowed_origins_patterns' => array_filter([
        env('CORS_ORIGIN_PATTERN'),  // e.g., '/^https:\/\/.*\.example\.com$/'
    ]),

    'allowed_headers' => ['Content-Type', 'X-Requested-With', 'Authorization', 'X-XSRF-TOKEN', 'X-HTTP-Method-Override', 'Accept'],

    'exposed_headers' => [],

    'max_age' => 86400,  // Cache preflight for 24 hours

    'supports_credentials' => true,
];
