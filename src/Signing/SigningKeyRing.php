<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Signing;

use RuntimeException;
use Teoprayoga\TeobiefyLaravelApiResponse\Exceptions\InvalidSignatureException;

class SigningKeyRing
{
    private const MIN_KEY_BYTES = 32;

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
        $rawKeys = config('teobiefy.signing.keys', []);
        $keys = [];

        if (is_array($rawKeys)) {
            foreach ($rawKeys as $kid => $material) {
                if (! is_string($material) || $material === '') {
                    continue;
                }
                $keys[(string) $kid] = self::decodeMaterial($material);
            }
        }

        $active = config('teobiefy.signing.active');
        $active = is_string($active) && $active !== '' ? $active : null;

        $legacyConfigured = config('teobiefy.signing.key');
        $legacyKey = is_string($legacyConfigured) && $legacyConfigured !== ''
            ? self::decodeMaterial($legacyConfigured)
            : null;

        return new self($keys, $active, $legacyKey);
    }

    /**
     * @return array{kid: ?string, bytes: string}
     */
    public function active(): array
    {
        if ($this->activeKid !== null && $this->keys !== []) {
            if (! array_key_exists($this->activeKid, $this->keys)) {
                throw new RuntimeException("Configured active signing kid [{$this->activeKid}] is not present in teobiefy.signing.keys.");
            }

            $bytes = $this->keys[$this->activeKid];
            $this->assertMinLength($bytes, $this->activeKid);

            return ['kid' => $this->activeKid, 'bytes' => $bytes];
        }

        if ($this->legacyKey === null) {
            throw new RuntimeException('Missing API payload signing key.');
        }

        $this->assertMinLength($this->legacyKey, null);

        return ['kid' => null, 'bytes' => $this->legacyKey];
    }

    /**
     * @throws InvalidSignatureException when the kid is unknown
     */
    public function resolve(?string $kid): string
    {
        if ($kid === null) {
            if ($this->legacyKey === null) {
                throw InvalidSignatureException::because('Signing envelope is missing [sig_kid] and no legacy signing key is configured.');
            }

            $this->assertMinLength($this->legacyKey, null);

            return $this->legacyKey;
        }

        if (! array_key_exists($kid, $this->keys)) {
            throw InvalidSignatureException::because('Unknown signing key id.');
        }

        $bytes = $this->keys[$kid];
        $this->assertMinLength($bytes, $kid);

        return $bytes;
    }

    private function assertMinLength(string $key, ?string $kid): void
    {
        if (strlen($key) < self::MIN_KEY_BYTES) {
            $label = $kid !== null ? "[{$kid}]" : '[legacy]';
            throw new RuntimeException('API payload signing key '.$label.' must be at least '.self::MIN_KEY_BYTES.' bytes.');
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
