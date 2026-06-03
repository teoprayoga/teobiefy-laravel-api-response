<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Encryption;

use RuntimeException;

class SodiumBackendFactory
{
    public function __construct(
        private readonly mixed $nativeAvailable = null,
        private readonly mixed $compatAvailable = null,
    ) {}

    public function make(string $driver, bool $allowCompatFallback): SodiumBackend
    {
        if ($this->nativeAvailable()) {
            return new NativeSodiumBackend;
        }

        if (! $allowCompatFallback) {
            throw new RuntimeException("Native sodium extension is required for API payload cipher [{$driver}].");
        }

        if (! $this->compatAvailable()) {
            throw new RuntimeException("Sodium compatibility fallback is enabled for API payload cipher [{$driver}], but paragonie/sodium_compat is not installed.");
        }

        return new SodiumCompatBackend;
    }

    private function nativeAvailable(): bool
    {
        if (is_callable($this->nativeAvailable)) {
            return (bool) call_user_func($this->nativeAvailable);
        }

        return extension_loaded('sodium')
            && function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')
            && function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt')
            && function_exists('sodium_crypto_aead_chacha20poly1305_ietf_encrypt')
            && function_exists('sodium_crypto_aead_chacha20poly1305_ietf_decrypt');
    }

    private function compatAvailable(): bool
    {
        if (is_callable($this->compatAvailable)) {
            return (bool) call_user_func($this->compatAvailable);
        }

        return class_exists(\ParagonIE_Sodium_Compat::class)
            && method_exists(\ParagonIE_Sodium_Compat::class, 'crypto_aead_xchacha20poly1305_ietf_encrypt')
            && method_exists(\ParagonIE_Sodium_Compat::class, 'crypto_aead_xchacha20poly1305_ietf_decrypt')
            && method_exists(\ParagonIE_Sodium_Compat::class, 'crypto_aead_chacha20poly1305_ietf_encrypt')
            && method_exists(\ParagonIE_Sodium_Compat::class, 'crypto_aead_chacha20poly1305_ietf_decrypt');
    }
}
