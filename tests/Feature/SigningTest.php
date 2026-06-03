<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Teoprayoga\TeobiefyLaravelApiResponse\Compression\ZstdCompressor;
use Teoprayoga\TeobiefyLaravelApiResponse\Encryption\PayloadCipher;
use Teoprayoga\TeobiefyLaravelApiResponse\Exceptions\InvalidSignatureException;
use Teoprayoga\TeobiefyLaravelApiResponse\PayloadTransformer;
use Teoprayoga\TeobiefyLaravelApiResponse\Profile;
use Teoprayoga\TeobiefyLaravelApiResponse\Signing\PayloadSigner;
use Teoprayoga\TeobiefyLaravelApiResponse\Tests\TestCase;

class SigningTest extends TestCase
{
    public function test_signed_response_envelope_includes_sig_fields(): void
    {
        $transformer = $this->transformer();
        $response = ['status' => 200, 'message' => 'OK', 'data' => ['name' => 'Teo']];

        $transformed = $transformer->transformResponse($response, 'data', Profile::from(Profile::SIGNED));

        $this->assertArrayHasKey('sig', $transformed);
        $this->assertSame('hmac-sha256', $transformed['sig_alg']);
        $this->assertArrayNotHasKey('sig_kid', $transformed);
        $this->assertArrayHasKey('data_comp', $transformed);
    }

    public function test_signed_request_verifies_and_strips_sig_fields(): void
    {
        $transformer = $this->transformer();
        $data = ['name' => 'Teo'];
        $response = ['status' => 200, 'message' => 'OK', 'data' => $data];

        $transformed = $transformer->transformResponse($response, 'data', Profile::from(Profile::SIGNED));

        $this->assertSame($data, $transformer->decodeRequest($transformed, Profile::from(Profile::SIGNED)));
    }

    public function test_tampered_signed_payload_is_rejected(): void
    {
        $transformer = $this->transformer();
        $response = ['status' => 200, 'message' => 'OK', 'data' => ['name' => 'Teo']];

        $transformed = $transformer->transformResponse($response, 'data', Profile::from(Profile::SIGNED));
        $tampered = $transformed;
        $tampered['data_comp'] = base64_encode(json_encode(['name' => 'Mallory'], JSON_THROW_ON_ERROR));

        $this->expectException(InvalidSignatureException::class);
        $this->expectExceptionMessage('Signature verification failed.');

        $transformer->decodeRequest($tampered, Profile::from(Profile::SIGNED));
    }

    public function test_unknown_sig_kid_rejected(): void
    {
        config()->set('teobiefy.signing.keys', [
            'v1' => base64_encode(str_repeat('a', 32)),
        ]);
        config()->set('teobiefy.signing.active', 'v1');

        $transformer = $this->transformer();
        $response = ['status' => 200, 'message' => 'OK', 'data' => ['name' => 'Teo']];
        $transformed = $transformer->transformResponse($response, 'data', Profile::from(Profile::SIGNED));

        $this->assertSame('v1', $transformed['sig_kid']);

        $tampered = $transformed;
        $tampered['sig_kid'] = 'unknown';

        $this->expectException(InvalidSignatureException::class);
        $this->expectExceptionMessage('Unknown signing key id.');

        $transformer->decodeRequest($tampered, Profile::from(Profile::SIGNED));
    }

    public function test_compressed_signed_roundtrips(): void
    {
        $transformer = $this->transformer();
        $data = ['content' => str_repeat('A', 1200)];
        $response = ['status' => 200, 'message' => 'OK', 'data' => $data];

        $transformed = $transformer->transformResponse($response, 'data', Profile::from(Profile::COMPRESSED_SIGNED));

        $this->assertSame('zstd', $transformed['compression']);
        $this->assertArrayHasKey('sig', $transformed);
        $this->assertSame($data, $transformer->decodeRequest($transformed, Profile::from(Profile::COMPRESSED_SIGNED)));
    }

    public function test_signing_key_command_outputs_base64_key(): void
    {
        $exitCode = Artisan::call('teobiefy:signing-key');

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('TEOBIEFY_SIGNING_KEY=base64:', $output);

        $base64 = trim(str_replace('TEOBIEFY_SIGNING_KEY=base64:', '', $output));
        $decoded = base64_decode($base64, true);
        $this->assertNotFalse($decoded);
        $this->assertSame(32, strlen($decoded));
    }

    public function test_disallowed_alg_rejected_even_with_valid_hmac(): void
    {
        $transformer = $this->transformer();
        $response = ['status' => 200, 'message' => 'OK', 'data' => ['name' => 'Teo']];
        $transformed = $transformer->transformResponse($response, 'data', Profile::from(Profile::SIGNED));

        $tampered = $transformed;
        $tampered['sig_alg'] = 'hmac-md5';

        $this->expectException(InvalidSignatureException::class);
        $this->expectExceptionMessage('Disallowed signing algorithm [hmac-md5].');

        $transformer->decodeRequest($tampered, Profile::from(Profile::SIGNED));
    }

    private function transformer(): PayloadTransformer
    {
        return new PayloadTransformer(new ZstdCompressor, new PayloadCipher, new PayloadSigner);
    }
}
