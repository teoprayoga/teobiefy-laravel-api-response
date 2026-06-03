<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Signing;

use RuntimeException;
use Teoprayoga\TeobiefyLaravelApiResponse\Exceptions\InvalidSignatureException;

class PayloadSigner
{
    private const ALLOWED_ALGORITHMS = ['hmac-sha256'];

    private ?SigningKeyRing $keyRing;

    public function __construct(?SigningKeyRing $keyRing = null)
    {
        $this->keyRing = $keyRing;
    }

    /**
     * Sign $material with the active signing key. Returns raw signature
     * bytes (caller is expected to base64-encode for transport).
     *
     * @return array{sig: string, alg: string, kid: ?string}
     */
    public function sign(string $material): array
    {
        $alg = $this->algorithm();
        $active = $this->keyRing()->active();

        return [
            'sig' => hash_hmac($this->hashName($alg), $material, $active['bytes'], true),
            'alg' => $alg,
            'kid' => $active['kid'],
        ];
    }

    /**
     * Verify $sigBase64 against $material using the key identified by
     * $kid (null = legacy single-key). Always compares with hash_equals
     * to avoid timing leaks.
     *
     * @throws InvalidSignatureException
     */
    public function verify(string $material, string $sigBase64, string $alg, ?string $kid): void
    {
        if (! in_array($alg, self::ALLOWED_ALGORITHMS, true)) {
            throw InvalidSignatureException::because("Disallowed signing algorithm [{$alg}].");
        }

        $sig = base64_decode($sigBase64, true);
        if ($sig === false || $sig === '') {
            throw InvalidSignatureException::because('Signature is not valid base64.');
        }

        $key = $this->keyRing()->resolve($kid);
        $computed = hash_hmac($this->hashName($alg), $material, $key, true);

        if (! hash_equals($computed, $sig)) {
            throw InvalidSignatureException::because('Signature verification failed.');
        }
    }

    private function algorithm(): string
    {
        $alg = (string) config('teobiefy.signing.algorithm', 'hmac-sha256');

        if (! in_array($alg, self::ALLOWED_ALGORITHMS, true)) {
            throw new RuntimeException("Disallowed signing algorithm [{$alg}].");
        }

        return $alg;
    }

    private function hashName(string $alg): string
    {
        return match ($alg) {
            'hmac-sha256' => 'sha256',
            default => throw new RuntimeException("Unsupported signing algorithm [{$alg}]."),
        };
    }

    private function keyRing(): SigningKeyRing
    {
        return $this->keyRing ?? SigningKeyRing::fromConfig();
    }
}
