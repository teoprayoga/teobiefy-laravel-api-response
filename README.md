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
| `data_comp` | Base64 JSON payload for compressed profiles |
| `data_enc` | Base64 encrypted payload for encrypted profiles |
| `nonce` | Base64 nonce used by encrypted profiles |
| `cipher` | Encryption driver recorded in the payload |
| `compression` | `none` or `zstd`, so receivers know whether decompression is required |

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

See [`config/teobiefy.php`](config/teobiefy.php) for all options (response keys, status stringification, compression, encryption, sodium_compat fallback toggle, etc.).

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
