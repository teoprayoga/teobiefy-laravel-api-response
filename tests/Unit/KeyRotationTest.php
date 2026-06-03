<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Tests\Unit;

use Teoprayoga\TeobiefyLaravelApiResponse\Encryption\PayloadCipher;
use Teoprayoga\TeobiefyLaravelApiResponse\Exceptions\InvalidPayloadException;
use Teoprayoga\TeobiefyLaravelApiResponse\Tests\TestCase;

class KeyRotationTest extends TestCase
{
    public function test_envelope_omits_kid_when_only_legacy_key_configured(): void
    {
        $cipher = new PayloadCipher;

        $encrypted = $cipher->encrypt('hello');

        $this->assertNull($encrypted['kid']);
        $this->assertSame('hello', $cipher->decrypt(
            $encrypted['ciphertext'],
            base64_encode($encrypted['nonce']),
            $encrypted['cipher'],
            $encrypted['kid'],
        ));
    }

    public function test_envelope_includes_kid_when_active_kid_set(): void
    {
        config()->set('teobiefy.encryption.keys', [
            'v1' => base64_encode(str_repeat('a', 32)),
        ]);
        config()->set('teobiefy.encryption.active', 'v1');

        $cipher = new PayloadCipher;
        $encrypted = $cipher->encrypt('hello');

        $this->assertSame('v1', $encrypted['kid']);
    }

    public function test_decrypts_payload_with_old_kid_after_rotation(): void
    {
        config()->set('teobiefy.encryption.keys', [
            'v1' => base64_encode(str_repeat('a', 32)),
            'v2' => base64_encode(str_repeat('b', 32)),
        ]);
        config()->set('teobiefy.encryption.active', 'v1');

        $cipher = new PayloadCipher;
        $encrypted = $cipher->encrypt('legacy-payload');
        $this->assertSame('v1', $encrypted['kid']);

        config()->set('teobiefy.encryption.active', 'v2');

        $decrypted = $cipher->decrypt(
            $encrypted['ciphertext'],
            base64_encode($encrypted['nonce']),
            $encrypted['cipher'],
            $encrypted['kid'],
        );

        $this->assertSame('legacy-payload', $decrypted);
    }

    public function test_unknown_kid_throws_invalid_payload_exception(): void
    {
        config()->set('teobiefy.encryption.keys', [
            'v1' => base64_encode(str_repeat('a', 32)),
        ]);
        config()->set('teobiefy.encryption.active', 'v1');

        $cipher = new PayloadCipher;
        $encrypted = $cipher->encrypt('hello');

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Unknown encryption key id.');

        $cipher->decrypt(
            $encrypted['ciphertext'],
            base64_encode($encrypted['nonce']),
            $encrypted['cipher'],
            'unknown-kid',
        );
    }

    public function test_legacy_envelope_without_kid_decrypts_when_keyring_has_kids(): void
    {
        $cipher = new PayloadCipher;
        $encrypted = $cipher->encrypt('legacy-payload');
        $this->assertNull($encrypted['kid']);

        config()->set('teobiefy.encryption.keys', [
            'v1' => base64_encode(str_repeat('b', 32)),
        ]);
        config()->set('teobiefy.encryption.active', 'v1');

        $decrypted = $cipher->decrypt(
            $encrypted['ciphertext'],
            base64_encode($encrypted['nonce']),
            $encrypted['cipher'],
            null,
        );

        $this->assertSame('legacy-payload', $decrypted);
    }
}
