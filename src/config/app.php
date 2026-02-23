<?php

return [
    'name' => env('APP_NAME', 'Formulare'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'asset_url' => env('ASSET_URL'),
    'timezone' => 'Europe/Bratislava',
    'locale' => 'sk',
    'fallback_locale' => 'en',
    'faker_locale' => 'sk_SK',
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
    'maintenance' => [
        'driver' => 'file',
    ],
];
