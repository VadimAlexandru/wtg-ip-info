<?php
return [
    'route' => '/ip-country',
    'redis' => [
        'host' => env('IPCOUNTRY_REDIS_HOST', '127.0.0.1'),
        'port' => env('IPCOUNTRY_REDIS_PORT', 6379),
        'database' => env('IPCOUNTRY_REDIS_DATABASE', null),
        'password' => env('IPCOUNTRY_REDIS_PASSWORD', null),
        'prefix' => env('IPCOUNTRY_REDIS_PREFIX', 'ip_country'),
    ],
];

