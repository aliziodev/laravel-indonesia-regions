<?php

return [
    'data_path' => env('INDONESIA_REGIONS_DATA_PATH', __DIR__.'/../data'),

    'database' => [
        'connection' => env('INDONESIA_REGIONS_DB_CONNECTION'),
    ],

    'api' => [
        'enabled' => env('INDONESIA_REGIONS_API_ENABLED', true),
        'prefix' => env('INDONESIA_REGIONS_API_PREFIX', 'api/indonesia-regions'),
        'middleware' => ['api'],
        'responder' => null,
    ],

    'cache' => [
        'store' => env('INDONESIA_REGIONS_CACHE_STORE'),
        'ttl' => (int) env('INDONESIA_REGIONS_CACHE_TTL', 86400),
        'prefix' => env('INDONESIA_REGIONS_CACHE_PREFIX', 'indonesia_regions'),
    ],
];
