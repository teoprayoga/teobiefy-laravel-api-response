<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse;

use JsonException;
use Teoprayoga\TeobiefyLaravelApiResponse\Compression\ZstdCompressor;
use Teoprayoga\TeobiefyLaravelApiResponse\Encryption\PayloadCipher;
use Teoprayoga\TeobiefyLaravelApiResponse\Exceptions\InvalidPayloadException;
use Teoprayoga\TeobiefyLaravelApiResponse\Exceptions\PayloadTooLargeException;
use Teoprayoga\TeobiefyLaravelApiResponse\Signing\PayloadSigner;

class PayloadTransformer
{
    private ?PayloadSigner $signer;

    public function __construct(
        private readonly ZstdCompressor $compressor,
        private readonly PayloadCipher $cipher,
        ?PayloadSigner $signer = null,
    ) {
        $this->signer = $signer;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public function transformResponse(array $response, string $dataKey, Profile $profile): array
    {
        if ($profile->isPlain()) {
            return $response;
        }

        $payload = json_encode($response[$dataKey] ?? null, JSON_THROW_ON_ERROR);
        unset($response[$dataKey]);

        $compression = 'none';

        if ($profile->compresses()) {
            if ($this->shouldCompress($payload)) {
                $payload = $this->compressor->compress($payload);
                $compression = 'zstd';
            }
        }

        if ($profile->encrypts()) {
            $encrypted = $this->cipher->encrypt($payload);

            $envelope = [
                'data_enc' => base64_encode($encrypted['ciphertext']),
                'nonce' => base64_encode($encrypted['nonce']),
                'cipher' => $encrypted['cipher'],
                'compression' => $profile->compresses() ? $compression : 'none',
            ];

            if (($encrypted['kid'] ?? null) !== null) {
                $envelope['kid'] = $encrypted['kid'];
            }

            return array_merge($response, $envelope);
        }

        $envelope = [
            'data_comp' => base64_encode($payload),
            'compression' => $compression,
        ];

        if ($profile->signs()) {
            $signature = $this->signer()->sign($payload);
            $envelope['sig'] = base64_encode($signature['sig']);
            $envelope['sig_alg'] = $signature['alg'];
            if ($signature['kid'] !== null) {
                $envelope['sig_kid'] = $signature['kid'];
            }
        }

        return array_merge($response, $envelope);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws InvalidPayloadException
     * @throws PayloadTooLargeException
     */
    public function decodeRequest(array $payload, Profile $profile): array
    {
        if ($profile->isPlain()) {
            return $payload;
        }

        $decoded = $this->extractEncodedPayload($payload, $profile);

        if ($profile->signs()) {
            $this->signer()->verify(
                $decoded,
                $this->requireString($payload, 'sig'),
                isset($payload['sig_alg']) && is_string($payload['sig_alg']) && $payload['sig_alg'] !== ''
                    ? $payload['sig_alg']
                    : 'hmac-sha256',
                isset($payload['sig_kid']) && is_string($payload['sig_kid']) && $payload['sig_kid'] !== ''
                    ? $payload['sig_kid']
                    : null,
            );
        }

        if ($profile->encrypts()) {
            $decoded = $this->cipher->decrypt(
                $decoded,
                $this->requireString($payload, 'nonce'),
                $payload['cipher'] ?? null,
                isset($payload['kid']) && is_string($payload['kid']) && $payload['kid'] !== '' ? $payload['kid'] : null,
            );
        }

        if ($profile->compresses() && $this->payloadCompression($payload) === 'zstd') {
            $decoded = $this->compressor->decompress($decoded);
        }

        try {
            $json = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidPayloadException::because('Payload is not valid JSON.', previous: $exception);
        }

        if (! is_array($json)) {
            throw InvalidPayloadException::because('Payload JSON must decode to an array.');
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractEncodedPayload(array $payload, Profile $profile): string
    {
        if ($profile->encrypts()) {
            return $this->base64Decode($this->requireString($payload, 'data_enc'), 'data_enc');
        }

        return $this->base64Decode($this->requireString($payload, 'data_comp'), 'data_comp');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requireString(array $payload, string $key): string
    {
        if (! isset($payload[$key]) || ! is_string($payload[$key]) || $payload[$key] === '') {
            throw InvalidPayloadException::because("Missing [{$key}] payload field.");
        }

        return $payload[$key];
    }

    private function base64Decode(string $payload, string $key): string
    {
        $decoded = base64_decode($payload, true);

        if ($decoded === false) {
            throw InvalidPayloadException::because("Payload field [{$key}] is not valid base64.");
        }

        return $decoded;
    }

    private function shouldCompress(string $payload): bool
    {
        $driver = config('teobiefy.compression.driver', 'zstd');

        if ($driver === 'none') {
            return false;
        }

        $minBytes = (int) config('teobiefy.compression.min_bytes', 1024);

        return $minBytes <= 0 || strlen($payload) >= $minBytes;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadCompression(array $payload): string
    {
        $compression = $payload['compression'] ?? 'zstd';

        if (! is_string($compression) || $compression === '') {
            throw InvalidPayloadException::because('Payload compression metadata is invalid.');
        }

        if (! in_array($compression, ['zstd', 'none'], true)) {
            throw InvalidPayloadException::because("Unsupported payload compression [{$compression}].");
        }

        return $compression;
    }

    private function signer(): PayloadSigner
    {
        return $this->signer ?? ($this->signer = new PayloadSigner);
    }
}
