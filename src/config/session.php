<?php

return [
    'driver' => env('SESSION_DRIVER', 'redis'),
    'lifetime' => env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt' => true,  // Encrypt session data for security
    'files' => storage_path('framework/sessions'),
    'connection' => 'default',
    'table' => 'sessions',
    'store' => null,
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', 'formulare_session'),
    'path' => '/',
    'domain' => env('SESSION_DOMAIN'),
    'secure' => env('SESSION_SECURE_COOKIE', true),  // Default to secure cookies
    'http_only' => true,
    'same_site' => 'lax',
    'partitioned' => false,
];
