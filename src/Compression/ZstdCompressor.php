<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Compression;

use RuntimeException;
use Teoprayoga\TeobiefyLaravelApiResponse\Exceptions\InvalidPayloadException;
use Teoprayoga\TeobiefyLaravelApiResponse\Exceptions\PayloadTooLargeException;

class ZstdCompressor
{
    public function compress(string $payload): string
    {
        $this->ensureAvailable();

        $compressed = zstd_compress($payload, (int) config('teobiefy.compression.level', 3));

        if (! is_string($compressed)) {
            throw new RuntimeException('Unable to compress API payload with zstd.');
        }

        return $compressed;
    }

    /**
     * @throws InvalidPayloadException
     * @throws PayloadTooLargeException
     */
    public function decompress(string $payload): string
    {
        $this->ensureAvailable();

        $decompressed = @zstd_uncompress($payload);

        if (! is_string($decompressed)) {
            throw InvalidPayloadException::because('Unable to decompress zstd payload.');
        }

        $maxBytes = (int) config('teobiefy.compression.max_decompressed_bytes', 10485760);

        if ($maxBytes > 0 && strlen($decompressed) > $maxBytes) {
            throw new PayloadTooLargeException('Payload too large');
        }

        return $decompressed;
    }

    private function ensureAvailable(): void
    {
        if (config('teobiefy.compression.driver', 'zstd') !== 'zstd') {
            throw new RuntimeException('Only zstd API payload compression is supported.');
        }

        if (! extension_loaded('zstd') || ! function_exists('zstd_compress') || ! function_exists('zstd_uncompress')) {
            throw new RuntimeException('The zstd PHP extension is required for API payload compression.');
        }
    }
}
