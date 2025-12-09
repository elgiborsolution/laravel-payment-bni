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

    // Konfigurasi SNAP (dipakai channel qris)
    'snap' => [
        'base_url' => env('BNI_SNAP_BASE_URL', 'https://merchant-api.qris-bni.com/apisnap'),
        'base_url_staging' => env('BNI_SNAP_BASE_URL_STAGING', 'https://qris-merchant-api.spesandbox.com/apisnap'),

        'version' => env('BNI_SNAP_VERSION', 'v1.0'),

        // kredensial SNAP
        'client_id' => env('BNI_SNAP_CLIENT_ID', env('BNI_CLIENT_ID')),
        'client_key' => env('BNI_SNAP_CLIENT_KEY', env('BNI_CLIENT_ID')),
        'client_secret' => env('BNI_SNAP_CLIENT_SECRET', ''),
        'partner_id' => env('BNI_SNAP_PARTNER_ID', ''),

        'private_key_path' => env('BNI_SNAP_PRIVATE_KEY_PATH', storage_path('app/bni/snap_private_key.pem')),
        'public_key_path' => env('BNI_SNAP_PUBLIC_KEY_PATH', storage_path('app/bni/snap_public_key.pem')),

        'signature_type' => (int) env('BNI_SNAP_SIGNATURE_TYPE', 1),

        'timeout' => (int) env('BNI_SNAP_TIMEOUT', env('BNI_TIMEOUT', 15)),
        'verify_ssl' => (bool) env('BNI_SNAP_VERIFY_SSL', env('BNI_VERIFY_SSL', true)),
    ],

    'qris' => [
        'merchant_id' => env('BNI_QRIS_MERCHANT_ID', ''),
        'terminal_id' => env('BNI_QRIS_TERMINAL_ID', ''),
        'path_access_token'  => env('BNI_QRIS_PATH_ACCESS_TOKEN', '/access-token/b2b'),
        'path_generate_qr'   => env('BNI_QRIS_PATH_GENERATE_QR', '/v1.0/debit/payment-qr/qr-mpm'),
        'path_query_payment' => env('BNI_QRIS_PATH_QUERY_PAYMENT', '/v1.0/debit/payment-qr/qr-mpm/status'),
    ],

    'schedule' => [
        'enabled' => env('BNI_SCHEDULE_ENABLED', false),
        'cron' => env('BNI_SCHEDULE_CRON', '*/5 * * * *'),
    ]
];
