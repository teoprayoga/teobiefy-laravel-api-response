<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Tests\Feature;

use Illuminate\Cache\CacheManager;
use Teoprayoga\TeobiefyLaravelApiResponse\Compression\ZstdCompressor;
use Teoprayoga\TeobiefyLaravelApiResponse\Encryption\PayloadCipher;
use Teoprayoga\TeobiefyLaravelApiResponse\Exceptions\InvalidPayloadException;
use Teoprayoga\TeobiefyLaravelApiResponse\Exceptions\ReplayDetectedException;
use Teoprayoga\TeobiefyLaravelApiResponse\PayloadTransformer;
use Teoprayoga\TeobiefyLaravelApiResponse\Profile;
use Teoprayoga\TeobiefyLaravelApiResponse\Replay\ReplayGuard;
use Teoprayoga\TeobiefyLaravelApiResponse\Signing\PayloadSigner;
use Teoprayoga\TeobiefyLaravelApiResponse\Tests\TestCase;

class ReplayProtectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('teobiefy.replay_protection.enabled', true);
        config()->set('teobiefy.replay_protection.window_seconds', 60);
        config()->set('teobiefy.replay_protection.nonce_ttl_seconds', 600);

        $this->app->make(CacheManager::class)->store()->flush();
    }

    public function test_replay_disabled_passes_payload_without_wrapping(): void
    {
        config()->set('teobiefy.replay_protection.enabled', false);

        $transformer = $this->transformer();
        $data = ['name' => 'Teo'];
        $response = ['status' => 200, 'message' => 'OK', 'data' => $data];

        $transformed = $transformer->transformResponse($response, 'data', Profile::from(Profile::ENCRYPTED));
        $this->assertSame($data, $transformer->decodeRequest($transformed, Profile::from(Profile::ENCRYPTED)));
    }

    public function test_replay_enabled_rejects_duplicate_rnonce(): void
    {
        $transformer = $this->transformer();
        $response = ['status' => 200, 'message' => 'OK', 'data' => ['name' => 'Teo']];

        $transformed = $transformer->transformResponse($response, 'data', Profile::from(Profile::ENCRYPTED));

        $first = $transformer->decodeRequest($transformed, Profile::from(Profile::ENCRYPTED));
        $this->assertSame(['name' => 'Teo'], $first);

        $this->expectException(ReplayDetectedException::class);
        $this->expectExceptionMessage('Payload replay nonce has already been used.');

        $transformer->decodeRequest($transformed, Profile::from(Profile::ENCRYPTED));
    }

    public function test_replay_enabled_rejects_stale_timestamp(): void
    {
        $guard = new ReplayGuard(
            $this->app->make(CacheManager::class)->store(),
            windowSeconds: 60,
            nonceTtlSeconds: 600,
            cachePrefix: 'teobiefy:rp:',
        );

        $this->expectException(ReplayDetectedException::class);
        $this->expectExceptionMessage('Payload timestamp is outside the replay window.');

        $guard->assert(time() - 9999, base64_encode(random_bytes(12)));
    }

    public function test_replay_enabled_signed_profile_also_protected(): void
    {
        $transformer = $this->transformer();
        $response = ['status' => 200, 'message' => 'OK', 'data' => ['name' => 'Teo']];

        $transformed = $transformer->transformResponse($response, 'data', Profile::from(Profile::SIGNED));

        $this->assertSame(['name' => 'Teo'], $transformer->decodeRequest($transformed, Profile::from(Profile::SIGNED)));

        $this->expectException(ReplayDetectedException::class);
        $transformer->decodeRequest($transformed, Profile::from(Profile::SIGNED));
    }

    public function test_replay_tampering_with_encrypted_payload_fails_before_replay_check(): void
    {
        $transformer = $this->transformer();
        $response = ['status' => 200, 'message' => 'OK', 'data' => ['name' => 'Teo']];

        $transformed = $transformer->transformResponse($response, 'data', Profile::from(Profile::ENCRYPTED));

        $tampered = $transformed;
        $tampered['data_enc'] = base64_encode('garbage'.base64_decode($transformed['data_enc'], true));

        $this->expectException(InvalidPayloadException::class);
        $transformer->decodeRequest($tampered, Profile::from(Profile::ENCRYPTED));
    }

    public function test_replay_guard_constructor_rejects_short_ttl(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('nonce_ttl_seconds must be at least 2 * window_seconds');

        new ReplayGuard(
            $this->app->make(CacheManager::class)->store(),
            windowSeconds: 60,
            nonceTtlSeconds: 90,
            cachePrefix: 'teobiefy:rp:',
        );
    }

    private function transformer(): PayloadTransformer
    {
        return new PayloadTransformer(
            new ZstdCompressor,
            new PayloadCipher,
            new PayloadSigner,
            ReplayGuard::fromConfig($this->app->make(CacheManager::class)),
        );
    }
}
