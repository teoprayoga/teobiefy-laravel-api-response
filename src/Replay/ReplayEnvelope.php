<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Replay;

use JsonException;
use Teoprayoga\TeobiefyLaravelApiResponse\Exceptions\InvalidPayloadException;

class ReplayEnvelope
{
    private const RNONCE_BYTES = 12;

    public static function wrap(string $innerPayload): string
    {
        return json_encode([
            'ts' => time(),
            'rnonce' => base64_encode(random_bytes(self::RNONCE_BYTES)),
            'payload' => base64_encode($innerPayload),
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{ts: int, rnonce: string, payload: string}
     *
     * @throws InvalidPayloadException
     */
    public static function unwrap(string $wrappedJson): array
    {
        try {
            $data = json_decode($wrappedJson, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw InvalidPayloadException::because('Replay envelope is not valid JSON.', previous: $e);
        }

        if (! is_array($data) || ! isset($data['ts'], $data['rnonce'], $data['payload'])) {
            throw InvalidPayloadException::because('Replay envelope is missing required fields.');
        }

        if (! is_int($data['ts']) || ! is_string($data['rnonce']) || ! is_string($data['payload'])) {
            throw InvalidPayloadException::because('Replay envelope has malformed fields.');
        }

        $payload = base64_decode($data['payload'], true);
        if ($payload === false) {
            throw InvalidPayloadException::because('Replay envelope payload is not valid base64.');
        }

        return [
            'ts' => $data['ts'],
            'rnonce' => $data['rnonce'],
            'payload' => $payload,
        ];
    }
}
