<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Encryption;

use RuntimeException;
use Teoprayoga\TeobiefyLaravelApiResponse\Exceptions\InvalidPayloadException;

class PayloadCipher
{
    private SodiumBackendFactory $sodiumBackends;

    public function __construct(?SodiumBackendFactory $sodiumBackends = null)
    {
        $this->sodiumBackends = $sodiumBackends ?? new SodiumBackendFactory;
    }

    /**
     * @return array{ciphertext: string, nonce: string, cipher: string}
     */
    public function encrypt(string $payload): array
    {
        $driver = $this->driver();
        $key = $this->key($this->keyBytes($driver));
        $nonce = random_bytes($this->nonceBytes($driver));

        return [
            'ciphertext' => $this->encryptWithDriver($driver, $payload, $nonce, $key),
            'nonce' => $nonce,
            'cipher' => $driver,
        ];
    }

    /**
     * @throws InvalidPayloadException
     */
    public function decrypt(string $payload, string $encodedNonce, mixed $envelopeCipher = null): string
    {
        $driver = $this->driver();

        if (is_string($envelopeCipher) && $envelopeCipher !== '' && $envelopeCipher !== $driver) {
            throw InvalidPayloadException::because("Payload cipher [{$envelopeCipher}] does not match route cipher [{$driver}].");
        }

        $nonce = $this->base64Decode($encodedNonce, 'nonce');
        $key = $this->key($this->keyBytes($driver));

        $decrypted = $this->decryptWithDriver($driver, $payload, $nonce, $key);

        if (! is_string($decrypted)) {
            throw InvalidPayloadException::because('Unable to decrypt API payload.');
        }

        return $decrypted;
    }

    private function driver(): string
    {
        return (string) config('teobiefy.encryption.driver', 'xchacha20-poly1305');
    }

    private function key(int $bytes): string
    {
        $configured = config('teobiefy.encryption.key') ?: config('app.libsodium_key');

        if (! is_string($configured) || $configured === '') {
            throw new RuntimeException('Missing API payload encryption key.');
        }

        if (str_starts_with($configured, 'base64:')) {
            $configured = substr($configured, strlen('base64:'));
        }

        $decoded = base64_decode($configured, true);
        $key = $decoded !== false ? $decoded : $configured;

        if (strlen($key) !== $bytes) {
            throw new RuntimeException("API payload encryption key must be {$bytes} bytes.");
        }

        return $key;
    }

    private function keyBytes(string $driver): int
    {
        return match ($driver) {
            'xchacha20-poly1305', 'chacha20-poly1305-ietf' => 32,
            'aes-256-gcm' => 32,
            default => throw new RuntimeException("Unsupported API payload cipher [{$driver}]."),
        };
    }

    private function nonceBytes(string $driver): int
    {
        return match ($driver) {
            'xchacha20-poly1305' => 24,
            'chacha20-poly1305-ietf' => 12,
            'aes-256-gcm' => 12,
            default => throw new RuntimeException("Unsupported API payload cipher [{$driver}]."),
        };
    }

    private function encryptWithDriver(string $driver, string $payload, string $nonce, string $key): string
    {
        if ($driver === 'aes-256-gcm') {
            return $this->encryptAesGcm($payload, $nonce, $key);
        }

        return $this->sodiumBackend($driver)->encrypt($driver, $payload, $nonce, $key);
    }

    private function decryptWithDriver(string $driver, string $payload, string $nonce, string $key): string|false
    {
        if ($driver === 'aes-256-gcm') {
            return $this->decryptAesGcm($payload, $nonce, $key);
        }

        return $this->sodiumBackend($driver)->decrypt($driver, $payload, $nonce, $key);
    }

    private function sodiumBackend(string $driver): SodiumBackend
    {
        return $this->sodiumBackends->make($driver, $this->allowSodiumCompatFallback());
    }

    private function allowSodiumCompatFallback(): bool
    {
        return (bool) config('teobiefy.encryption.allow_sodium_compat_fallback', false);
    }

    private function encryptAesGcm(string $payload, string $nonce, string $key): string
    {
        $tag = '';
        $ciphertext = openssl_encrypt($payload, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);

        if (! is_string($ciphertext)) {
            throw new RuntimeException('Unable to encrypt API payload with aes-256-gcm.');
        }

        return $ciphertext.$tag;
    }

    private function decryptAesGcm(string $payload, string $nonce, string $key): string|false
    {
        if (strlen($payload) < 16) {
            return false;
        }

        $tag = substr($payload, -16);
        $ciphertext = substr($payload, 0, -16);

        return openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
    }

    private function base64Decode(string $payload, string $key): string
    {
        $decoded = base64_decode($payload, true);

        if ($decoded === false) {
            throw InvalidPayloadException::because("Payload field [{$key}] is not valid base64.");
        }

        return $decoded;
    }
}
