# Changelog

All notable changes to `teoprayoga/teobiefy-laravel-api-response` are documented in this file.

The format is based on [Keep a Changelog 1.1.0](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning 2.0.0](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2026-06-03

First public release on Packagist.

### Added

- API response helpers: `api()`, `ok()`, `success()`, `api()->response()`, `api()->ok()`, `api()->notFound()`, `api()->validation()`, `api()->forbidden()`, `api()->error()`.
- Per-route payload profiles resolved by `RouteProfileResolver`: `plain`, `compressed`, `encrypted`, `compressed_encrypted`, `signed`, `compressed_signed`.
- AEAD encryption with three drivers: `xchacha20-poly1305` (default, 24-byte nonce), `chacha20-poly1305-ietf`, and `aes-256-gcm`.
- Zstd compression with `min_bytes` threshold and `max_decompressed_bytes` guard against decompression bombs.
- Encryption key rotation via optional envelope field `kid`; envelopes stay byte-identical with legacy single-key deployments until `encryption.keys[]` is configured.
- Profile resolution via PHP attributes: `#[ResponseProfile('...')]` and `#[RequestProfile('...')]` on controller classes and methods, with priority over `route_profiles` patterns.
- HMAC-SHA256 response signing through `PayloadSigner`, with a separate signing key ring (`SigningKeyRing`) that supports `sig_kid` rotation. Signed envelope fields: `sig`, `sig_alg`, `sig_kid`.
- Optional replay protection (`ReplayGuard`) — `ts` + `rnonce` wrapped inside the AEAD/HMAC scope, atomic check-and-set against the Laravel cache, configurable skew window and nonce TTL.
- Inbound middleware `PayloadDecryptMiddleware`: maps `PayloadTooLargeException` to 413, `ReplayDetectedException` to 409, `InvalidSignatureException` to 401 (with `WWW-Authenticate: TeobiefySig`), and other `InvalidPayloadException` to 406.
- Artisan commands: `teobiefy:key` and `teobiefy:signing-key` for generating base64 32-byte keys.
- GitHub Actions CI matrix: PHP 8.1 / 8.2 / 8.3 against Laravel 10 / 11 / 12.
- Configuration namespace `teobiefy.*` (replaces the prerelease `api.*` namespace).
- `illuminate/cache` declared explicitly in `require` (used by `ReplayGuard`).

### Notes

- Default profile remains `plain`; key rotation, signing, and replay protection are all opt-in via configuration.
- See [`UPGRADE.md`](UPGRADE.md) for guidance migrating from internal prerelease usage.

[Unreleased]: https://github.com/teoprayoga/teobiefy-laravel-api-response/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/teoprayoga/teobiefy-laravel-api-response/releases/tag/v0.2.0
