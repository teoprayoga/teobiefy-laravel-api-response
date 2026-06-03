<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Encryption;

use RuntimeException;
use Teoprayoga\TeobiefyLaravelApiResponse\Exceptions\InvalidPayloadException;

class KeyRing
{
    /**
     * @param  array<string, string>  $keys  raw key bytes keyed by kid
     */
    public function __construct(
        private readonly array $keys,
        private readonly ?string $activeKid,
        private readonly ?string $legacyKey,
    ) {}

    public static function fromConfig(): self
    {
        $rawKeys = config('teobiefy.encryption.keys', []);
        $keys = [];

        if (is_array($rawKeys)) {
            foreach ($rawKeys as $kid => $material) {
                if (! is_string($material) || $material === '') {
                    continue;
                }
                $keys[(string) $kid] = self::decodeMaterial($material);
            }
        }

        $active = config('teobiefy.encryption.active');
        $active = is_string($active) && $active !== '' ? $active : null;

        $legacyConfigured = config('teobiefy.encryption.key') ?: config('app.libsodium_key');
        $legacyKey = is_string($legacyConfigured) && $legacyConfigured !== ''
            ? self::decodeMaterial($legacyConfigured)
            : null;

        return new self($keys, $active, $legacyKey);
    }

    /**
     * Returns the active key + kid. `kid` is null when the ring runs in
     * legacy single-key mode so the envelope stays byte-identical with
     * pre-rotation deployments.
     *
     * @return array{kid: ?string, bytes: string}
     */
    public function active(int $expectedBytes): array
    {
        if ($this->activeKid !== null && $this->keys !== []) {
            if (! array_key_exists($this->activeKid, $this->keys)) {
                throw new RuntimeException("Configured active encryption kid [{$this->activeKid}] is not present in teobiefy.encryption.keys.");
            }

            $bytes = $this->keys[$this->activeKid];
            $this->assertLength($bytes, $expectedBytes, $this->activeKid);

            return ['kid' => $this->activeKid, 'bytes' => $bytes];
        }

        if ($this->legacyKey === null) {
            throw new RuntimeException('Missing API payload encryption key.');
        }

        $this->assertLength($this->legacyKey, $expectedBytes, null);

        return ['kid' => null, 'bytes' => $this->legacyKey];
    }

    /**
     * Resolve the raw key bytes for a given kid. `null` resolves to the
     * legacy single-key fallback so envelopes encrypted before key rotation
     * continue to decrypt.
     *
     * @throws InvalidPayloadException when the kid is unknown to the ring
     * @throws RuntimeException when null is given but no legacy key is configured
     */
    public function resolve(?string $kid, int $expectedBytes): string
    {
        if ($kid === null) {
            if ($this->legacyKey === null) {
                throw InvalidPayloadException::because('Encryption envelope is missing [kid] and no legacy key is configured.');
            }

            $this->assertLength($this->legacyKey, $expectedBytes, null);

            return $this->legacyKey;
        }

        if (! array_key_exists($kid, $this->keys)) {
            throw InvalidPayloadException::because('Unknown encryption key id.');
        }

        $bytes = $this->keys[$kid];
        $this->assertLength($bytes, $expectedBytes, $kid);

        return $bytes;
    }

    private function assertLength(string $key, int $expected, ?string $kid): void
    {
        if (strlen($key) !== $expected) {
            $label = $kid !== null ? "[{$kid}]" : '[legacy]';
            throw new RuntimeException("API payload encryption key {$label} must be {$expected} bytes.");
        }
    }

    private static function decodeMaterial(string $material): string
    {
        if (str_starts_with($material, 'base64:')) {
            $material = substr($material, strlen('base64:'));
        }

        $decoded = base64_decode($material, true);

        return $decoded !== false ? $decoded : $material;
    }
}
