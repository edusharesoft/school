<?php

return [

    'name' => env('APP_NAME', 'Shataj School & College'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    /*
    | Bangladesh Standard Time is the platform default (master prompt §34).
    */
    'timezone' => env('APP_TIMEZONE', 'Asia/Dhaka'),

    'currency' => env('APP_CURRENCY', 'BDT'),

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    'supported_locales' => array_filter(explode(',', (string) env('APP_SUPPORTED_LOCALES', 'en,bn'))),

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
