<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Encryption;

use RuntimeException;

class NativeSodiumBackend implements SodiumBackend
{
    public function encrypt(string $driver, string $payload, string $nonce, string $key): string
    {
        return match ($driver) {
            'xchacha20-poly1305' => sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($payload, '', $nonce, $key),
            'chacha20-poly1305-ietf' => sodium_crypto_aead_chacha20poly1305_ietf_encrypt($payload, '', $nonce, $key),
            default => throw new RuntimeException("Unsupported API payload cipher [{$driver}]."),
        };
    }

    public function decrypt(string $driver, string $payload, string $nonce, string $key): string|false
    {
        return match ($driver) {
            'xchacha20-poly1305' => sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($payload, '', $nonce, $key),
            'chacha20-poly1305-ietf' => sodium_crypto_aead_chacha20poly1305_ietf_decrypt($payload, '', $nonce, $key),
            default => throw new RuntimeException("Unsupported API payload cipher [{$driver}]."),
        };
    }
}
