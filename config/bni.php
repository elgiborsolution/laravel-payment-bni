<?php

return [
    'hostname' => env('BNI_HOSTNAME', 'api.bni-ecollection.com'),
    'hostname_staging' => env('BNI_HOSTNAME_STAGING', 'apibeta.bni-ecollection.com'),
    'port' => (int) env('BNI_PORT', 443),
    'origin' => env('BNI_ORIGIN', 'your-origin'),
    'client_id' => env('BNI_CLIENT_ID', '320'),
    'timeout' => (int) env('BNI_TIMEOUT', 15),
    'verify_ssl' => (bool) env('BNI_VERIFY_SSL', true),

    'routes' => [
        'prefix' => env('BNI_ROUTE_PREFIX', ''),
        'middleware' => ['api'],
    ],

    'qris' => [
        'merchant_id' => env('BNI_QRIS_MERCHANT_ID', ''),
        'terminal_id' => env('BNI_QRIS_TERMINAL_ID', ''),
        'path_create_dynamic' => '/qris/create',
        'path_inquiry_status' => '/qris/inquiry',
    ],

    'schedule' => [
        'enabled' => env('BNI_SCHEDULE_ENABLED', true),
        'cron' => env('BNI_SCHEDULE_CRON', '*/5 * * * *'),
    ]
];
