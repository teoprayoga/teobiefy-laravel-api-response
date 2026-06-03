<?php

return [
    'stringify' => false,
    'match_status' => true,
    'include_data_count' => true,

    'keys' => [
        'status' => 'status',
        'message' => 'message',
        'data' => 'data',
        'data_count' => 'data_count',
    ],

    'response' => [
        'default_profile' => 'plain',
        'route_profiles' => [],
    ],

    'request' => [
        'default_profile' => 'plain',
        'route_profiles' => [],
    ],

    'compression' => [
        'driver' => 'zstd',
        'level' => 3,
        'min_bytes' => 1024,
        'max_decompressed_bytes' => 10485760,
    ],

    'encryption' => [
        'driver' => 'xchacha20-poly1305',
        'key' => env('TEOBIEFY_ENCRYPTION_KEY'),
        'allow_sodium_compat_fallback' => false,
    ],
];
