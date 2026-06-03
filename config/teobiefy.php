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

        // Legacy single-key fallback. Used when `keys` is empty or when
        // decrypting envelopes that don't carry a `kid` (e.g. payloads
        // produced before key rotation was enabled).
        'key' => env('TEOBIEFY_ENCRYPTION_KEY'),

        // Named keys for rotation. Each value is a 32-byte key, optionally
        // base64-encoded with a `base64:` prefix. The `active` kid below
        // selects which one is used for new encryptions; all listed kids
        // can still be used to decrypt older envelopes.
        'keys' => [
            // 'v1' => env('TEOBIEFY_ENCRYPTION_KEY_V1'),
            // 'v2' => env('TEOBIEFY_ENCRYPTION_KEY_V2'),
        ],

        // When set and present in `keys`, envelopes carry this `kid` and
        // use the matching key. When null, the legacy single-key mode
        // applies and `kid` is omitted from the envelope.
        'active' => env('TEOBIEFY_ENCRYPTION_ACTIVE_KID'),

        'allow_sodium_compat_fallback' => false,
    ],
];
