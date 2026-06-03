<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Tests\Unit;

use Teoprayoga\TeobiefyLaravelApiResponse\Compression\ZstdCompressor;
use Teoprayoga\TeobiefyLaravelApiResponse\Encryption\PayloadCipher;
use Teoprayoga\TeobiefyLaravelApiResponse\PayloadTransformer;
use Teoprayoga\TeobiefyLaravelApiResponse\Profile;
use Teoprayoga\TeobiefyLaravelApiResponse\Tests\TestCase;

class PayloadTransformerTest extends TestCase
{
    public function test_compressed_response_below_min_bytes_uses_raw_json_with_none_metadata(): void
    {
        $transformer = $this->transformer();
        $response = ['status' => 200, 'message' => 'OK', 'data' => ['name' => 'Teo']];

        $transformed = $transformer->transformResponse($response, 'data', Profile::from(Profile::COMPRESSED));

        $this->assertSame('none', $transformed['compression']);
        $this->assertSame(json_encode($response['data'], JSON_THROW_ON_ERROR), base64_decode($transformed['data_comp'], true));
    }

    public function test_compressed_response_at_min_bytes_uses_zstd_and_roundtrips(): void
    {
        $transformer = $this->transformer();
        $data = ['content' => str_repeat('A', 1200)];
        $response = ['status' => 200, 'message' => 'OK', 'data' => $data];

        $transformed = $transformer->transformResponse($response, 'data', Profile::from(Profile::COMPRESSED));

        $this->assertSame('zstd', $transformed['compression']);
        $this->assertSame($data, $transformer->decodeRequest($transformed, Profile::from(Profile::COMPRESSED)));
    }

    public function test_compressed_encrypted_response_below_min_bytes_encrypts_raw_json(): void
    {
        $transformer = $this->transformer();
        $data = ['name' => 'Teo'];
        $response = ['status' => 200, 'message' => 'OK', 'data' => $data];

        $transformed = $transformer->transformResponse($response, 'data', Profile::from(Profile::COMPRESSED_ENCRYPTED));

        $this->assertSame('none', $transformed['compression']);
        $this->assertSame($data, $transformer->decodeRequest($transformed, Profile::from(Profile::COMPRESSED_ENCRYPTED)));
    }

    public function test_missing_compression_metadata_defaults_to_zstd_for_backward_compatibility(): void
    {
        $transformer = $this->transformer();
        config()->set('teobiefy.compression.min_bytes', 0);
        $response = ['status' => 200, 'message' => 'OK', 'data' => ['legacy' => true]];

        $transformed = $transformer->transformResponse($response, 'data', Profile::from(Profile::COMPRESSED));
        unset($transformed['compression']);

        $this->assertSame(['legacy' => true], $transformer->decodeRequest($transformed, Profile::from(Profile::COMPRESSED)));
    }

    public function test_compressed_response_with_none_driver_uses_raw_json_without_zstd(): void
    {
        config()->set('teobiefy.compression.driver', 'none');
        config()->set('teobiefy.compression.min_bytes', 0);

        $transformer = $this->transformer();
        $data = ['content' => str_repeat('A', 1200)];
        $response = ['status' => 200, 'message' => 'OK', 'data' => $data];

        $transformed = $transformer->transformResponse($response, 'data', Profile::from(Profile::COMPRESSED));

        $this->assertSame('none', $transformed['compression']);
        $this->assertSame(json_encode($data, JSON_THROW_ON_ERROR), base64_decode($transformed['data_comp'], true));
        $this->assertSame($data, $transformer->decodeRequest($transformed, Profile::from(Profile::COMPRESSED)));
    }

    private function transformer(): PayloadTransformer
    {
        return new PayloadTransformer(new ZstdCompressor, new PayloadCipher);
    }
}
