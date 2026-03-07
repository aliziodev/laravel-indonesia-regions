<?php

return [
    'cache' => [
        'store' => env('INDONESIA_REGIONS_CACHE_STORE'),
        'ttl' => (int) env('INDONESIA_REGIONS_CACHE_TTL', 86400),
        'prefix' => env('INDONESIA_REGIONS_CACHE_PREFIX', 'indonesia_regions'),
    ],
];
