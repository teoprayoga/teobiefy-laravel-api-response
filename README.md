# Teobiefy Laravel API Response

Laravel API response helpers with server-configured payload **compression** (zstd) and **authenticated encryption** (xchacha20-poly1305 / chacha20-poly1305-ietf via libsodium).

Inspired by [`obiefy/api-response`](https://github.com/obiefy/api-response), with first-class support for per-route payload profiles (plain / compressed / encrypted / compressed_encrypted).

## Requirements

- PHP `^8.1`
- Laravel 10, 11, or 12
- `ext-json`, `ext-zstd`
- `ext-sodium` (recommended) **or** `paragonie/sodium_compat` (optional fallback)

## Installation

```bash
composer require teoprayoga/teobiefy-laravel-api-response
```

The service provider and `API` facade are auto-discovered.

Publish the config:

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
| `compressed` | zstd compression on `data` |
| `encrypted` | libsodium AEAD on `data`, replaced with `data_enc` + `nonce` |
| `compressed_encrypted` | compress, then encrypt |

Configure per-route in `config/api.php`:

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

## Encryption key

Set a base64-encoded 32-byte key in `.env`:

```env
TEOBIEFY_ENCRYPTION_KEY=base64:...
```

Generate one:

```bash
php -r 'echo "base64:" . base64_encode(random_bytes(32)) . PHP_EOL;'
```

## Config reference

See [`config/api.php`](config/api.php) for all options (response keys, status stringification, compression level, max decompressed bytes, sodium_compat fallback toggle, etc.).

## License

MIT — see [LICENSE](LICENSE).
