# Teobiefy Laravel API Response

Laravel API response helpers with server-configured payload **compression** (zstd) and **authenticated encryption** (xchacha20-poly1305 / chacha20-poly1305-ietf via libsodium).

Inspired by [`obiefy/api-response`](https://github.com/obiefy/api-response), with first-class support for per-route payload profiles (plain / compressed / encrypted / compressed_encrypted).

## Requirements

- PHP `^8.1`
- Laravel 10, 11, or 12
- `ext-json`
- `ext-zstd` only when `teobiefy.compression.driver` is `zstd`
- `ext-sodium` (recommended) **or** `paragonie/sodium_compat` (optional fallback)

## Installation

```bash
composer require teoprayoga/teobiefy-laravel-api-response
```

The service provider and `API` facade are auto-discovered.

Publish the config to `config/teobiefy.php`:

```bash
php artisan vendor:publish --tag=api-response
```

Optionally publish translations:

```bash
php artisan vendor:publish --tag=api-response-lang
```

## Quick start

```php
return api()->ok('Success message', $data);
return api()->response(400, 'Bad request', $errors);
return api()->validation('Validation failed', $errors);
return api()->notFound();
return api()->forbidden();
return api()->error();
```

Global helpers `ok()` and `success()` are also available.

## Payload profiles

Each request/response can be transformed by a named profile resolved from the matched route:

| Profile | Behavior |
| --- | --- |
| `plain` | No transformation |
| `compressed` | Optional zstd compression on `data` |
| `encrypted` | libsodium AEAD on `data`, replaced with `data_enc` + `nonce` |
| `compressed_encrypted` | compress, then encrypt |
| `signed` | HMAC-SHA256 tag attached as `sig`; integrity without encryption |
| `compressed_signed` | compress, then attach HMAC tag |

Configure per-route in `config/teobiefy.php`:

```php
'response' => [
    'default_profile' => 'plain',
    'route_profiles' => [
        'api/v1/users/*'   => 'encrypted',
        'api/v1/reports/*' => 'compressed_encrypted',
    ],
],

'request' => [
    'default_profile' => 'plain',
    'route_profiles' => [
        'api/v1/auth/*' => 'encrypted',
    ],
],
```

Inbound decryption is handled by `PayloadDecryptMiddleware` — register it in your HTTP kernel for routes that accept encrypted payloads.

Transformed response/request envelopes include:

| Field | Meaning |
| --- | --- |
| `data_comp` | Base64 JSON payload for compressed and signed profiles |
| `data_enc` | Base64 encrypted payload for encrypted profiles |
| `nonce` | Base64 nonce used by encrypted profiles |
| `cipher` | Encryption driver recorded in the payload |
| `compression` | `none` or `zstd`, so receivers know whether decompression is required |
| `kid` | Encryption key id; emitted only when key rotation is configured |
| `sig` / `sig_alg` / `sig_kid` | HMAC tag, algorithm, and signing key id for signed profiles |

## Profile via PHP attribute

In addition to the `route_profiles` map, you can mark a controller method or
controller class directly:

```php
use Teoprayoga\TeobiefyLaravelApiResponse\Attributes\RequestProfile;
use Teoprayoga\TeobiefyLaravelApiResponse\Attributes\ResponseProfile;
use Teoprayoga\TeobiefyLaravelApiResponse\Profile;

#[RequestProfile(Profile::ENCRYPTED)]
class UserController
{
    #[ResponseProfile(Profile::COMPRESSED_ENCRYPTED)]
    public function show(int $id) { /* ... */ }
}
```

Resolution order: **attribute on method → attribute on class → `route_profiles`
pattern match → `default_profile`**. Closure routes fall through to the config
pattern stage.

## Key rotation

To rotate the encryption key without breaking outstanding payloads, configure
a named keyring and pick an active key id:

```php
'encryption' => [
    'driver' => 'xchacha20-poly1305',
    'key' => env('TEOBIEFY_ENCRYPTION_KEY'),               // legacy fallback
    'keys' => [
        'v1' => env('TEOBIEFY_ENCRYPTION_KEY_V1'),
        'v2' => env('TEOBIEFY_ENCRYPTION_KEY_V2'),
    ],
    'active' => env('TEOBIEFY_ENCRYPTION_ACTIVE_KID'),     // e.g. 'v2'
],
```

Envelopes encrypted with `active = 'v2'` carry `"kid": "v2"`; rotate by adding
`v3` to `keys` and setting `active = 'v3'`. Older envelopes with `kid = v1` or
`kid = v2` continue to decrypt as long as those keys remain in `keys`.

If `keys` is empty and `active` is null, the package runs in legacy
single-key mode and the envelope omits `kid` (byte-for-byte identical with
pre-rotation deployments).

## Response signing

Use `signed` / `compressed_signed` profiles when you want integrity
without confidentiality (e.g. publicly cacheable responses that must not be
tampered with).

```php
'signing' => [
    'algorithm' => 'hmac-sha256',
    'key' => env('TEOBIEFY_SIGNING_KEY'),                  // legacy fallback
    'keys' => [
        'v1' => env('TEOBIEFY_SIGNING_KEY_V1'),
    ],
    'active' => env('TEOBIEFY_SIGNING_ACTIVE_KID'),
],
```

Generate a key:

```bash
php artisan teobiefy:signing-key
```

On inbound signed requests, `PayloadDecryptMiddleware` verifies before
decompression and rejects with **HTTP 401** plus
`WWW-Authenticate: TeobiefySig` on tampering, unknown `sig_kid`, or an
algorithm that is not in the allowlist.

AEAD-encrypted profiles already carry an integrity tag, so the package
intentionally does not ship an `encrypted_signed` profile.

## Replay protection

Optional, off by default. When enabled, encrypted and signed payloads wrap
their inner content with a `{ts, rnonce, payload}` envelope **inside** the
AEAD/HMAC scope, and inbound requests are rejected if (a) the timestamp falls
outside the configured window or (b) the random nonce has already been seen.
Plain and compressed-only profiles are never wrapped (no integrity guarantee).

```php
'replay_protection' => [
    'enabled' => env('TEOBIEFY_REPLAY_PROTECTION', false),
    'cache_store' => null,            // null = default cache
    'cache_prefix' => 'teobiefy:rp:',
    'window_seconds' => 300,
    'nonce_ttl_seconds' => 600,       // must be >= 2 * window_seconds
],
```

Replay failures are reported as **HTTP 409 Conflict**. The nonce store uses
the Laravel cache (`Repository::add()` for atomic check-and-set), so the
`cache_store` should be one that is shared across workers in production
(e.g. Redis); the default `array` driver only protects within a single
process.

## Encryption key

Set a base64-encoded 32-byte key in `.env`:

```env
TEOBIEFY_ENCRYPTION_KEY=base64:...
```

Generate one:

```bash
php artisan teobiefy:key
```

## Config reference

See [`config/teobiefy.php`](config/teobiefy.php) for all options (response
keys, status stringification, compression, encryption, signing,
replay_protection, sodium_compat fallback toggle, etc.).

Compression options:

```php
'compression' => [
    'driver' => 'zstd', // zstd or none
    'level' => 3,
    'min_bytes' => 1024,
    'max_decompressed_bytes' => 10485760,
],
```

Set `driver` to `none` to keep compressed profiles in the same envelope format without requiring `ext-zstd`. `min_bytes` controls the smallest JSON payload that will be zstd-compressed; smaller payloads are sent with `compression: none`.

Encryption uses `TEOBIEFY_ENCRYPTION_KEY` by default:

```php
'encryption' => [
    'driver' => 'xchacha20-poly1305',
    'key' => env('TEOBIEFY_ENCRYPTION_KEY'),
    'allow_sodium_compat_fallback' => false,
],
```

## Migrating from `config/api.php`

This release renames the package config namespace from `api` to `teobiefy` to avoid colliding with application config. Move any previous package settings from `config/api.php` to `config/teobiefy.php`, then update custom runtime reads from `config('api...')` to `config('teobiefy...')`.

## License

MIT — see [LICENSE](LICENSE).
