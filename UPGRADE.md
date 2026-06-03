# Upgrade Guide

This document lists upgrade steps grouped by target version (newest first).
See [`CHANGELOG.md`](CHANGELOG.md) for the full feature-level changes per release.

## Upgrading to 0.2.0

### Config namespace renamed: `api` → `teobiefy`

The published config file moved from `config/api.php` to `config/teobiefy.php`
to avoid colliding with application-level `api.*` settings.

- Republish the config:
  ```bash
  php artisan vendor:publish --tag=api-response
  ```
- Move any customisations from your old `config/api.php` into the new
  `config/teobiefy.php`.
- Update every runtime reference: `config('api.*')` → `config('teobiefy.*')`.

### Env var renamed: `LIBSODIUM_KEY` → `TEOBIEFY_ENCRYPTION_KEY`

The package still falls back to `config('app.libsodium_key')` automatically,
so no code change is required, but the canonical env variable is now
`TEOBIEFY_ENCRYPTION_KEY`. Generate a fresh key when convenient:

```bash
php artisan teobiefy:key
```

### Hardcoded route encryption list removed

Previously a static list of ~12 route names inside the helper decided which
endpoints were encrypted. That list is gone — express the same intent via
configuration or attributes.

Option A — config (`config/teobiefy.php`):

```php
'response' => [
    'default_profile' => 'plain',
    'route_profiles' => [
        'auth/*'                 => 'encrypted',
        'users/*'                => 'encrypted',
        'evaluation-summaries/*' => 'compressed_encrypted',
        // …port the rest of your old list here
    ],
],

'request' => [
    'default_profile' => 'plain',
    'route_profiles' => [
        'auth/*' => 'encrypted',
    ],
],
```

Option B — PHP attribute on the controller method or class:

```php
use Teoprayoga\TeobiefyLaravelApiResponse\Attributes\ResponseProfile;
use Teoprayoga\TeobiefyLaravelApiResponse\Profile;

class UserController
{
    #[ResponseProfile(Profile::ENCRYPTED)]
    public function show(int $id) { /* ... */ }
}
```

Attribute resolution wins over config patterns, so you can migrate the list
incrementally.

### Optional new features — enable as needed

| Feature | How to enable |
| --- | --- |
| Key rotation | Populate `teobiefy.encryption.keys` and set `teobiefy.encryption.active`. Without this, legacy single-key mode stays in effect and the envelope omits `kid`. |
| Response signing | Run `php artisan teobiefy:signing-key`, set `TEOBIEFY_SIGNING_KEY`, then assign the `signed` / `compressed_signed` profile via `route_profiles` or attribute. |
| Replay protection | Set `TEOBIEFY_REPLAY_PROTECTION=true`. It activates automatically for profiles that encrypt or sign; use a cache store shared across workers (e.g. Redis) in production. |

### Wire-protocol envelope changes (backward-compatible)

- Encrypted envelope: new optional field `kid`. Missing → legacy single-key
  decryption path (unchanged byte layout for old payloads).
- Signed profiles: new fields `sig`, `sig_alg`, `sig_kid`.
- Replay protection: when enabled, an internal envelope
  `{ts, rnonce, payload}` lives **inside** the AEAD plaintext / HMAC
  signing material — wrap/unwrap is transparent when both ends use this
  package.

### HTTP status codes from `PayloadDecryptMiddleware`

| Failure | Status | Notes |
| --- | --- | --- |
| `PayloadTooLargeException` | 413 | unchanged |
| `ReplayDetectedException` | **409** | duplicate `rnonce` or stale timestamp |
| `InvalidSignatureException` | **401** | adds `WWW-Authenticate: TeobiefySig` |
| Other `InvalidPayloadException` | 406 | unchanged |

## Upgrading from prereleases (before 0.2.0)

Not a public target. See the `CHANGELOG.md` entry for `[0.2.0] - First public
release on Packagist` for the full feature baseline.
