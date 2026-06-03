<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Teoprayoga\TeobiefyLaravelApiResponse\Encryption\PayloadCipher;
use Teoprayoga\TeobiefyLaravelApiResponse\Tests\TestCase;

class TeobiefyKeyCommandTest extends TestCase
{
    public function test_key_command_prints_base64_encryption_key(): void
    {
        $exitCode = Artisan::call('teobiefy:key');
        $output = trim(Artisan::output());

        $this->assertSame(0, $exitCode);
        $this->assertStringStartsWith('TEOBIEFY_ENCRYPTION_KEY=base64:', $output);
        $this->assertSame(32, strlen(base64_decode(substr($output, strlen('TEOBIEFY_ENCRYPTION_KEY=base64:')), true)));
    }

    public function test_generated_base64_key_can_be_used_by_payload_cipher(): void
    {
        Artisan::call('teobiefy:key');
        $output = trim(Artisan::output());
        config()->set('teobiefy.encryption.key', substr($output, strlen('TEOBIEFY_ENCRYPTION_KEY=')));

        $cipher = new PayloadCipher;
        $encrypted = $cipher->encrypt('secret');

        $this->assertSame('secret', $cipher->decrypt(
            $encrypted['ciphertext'],
            base64_encode($encrypted['nonce']),
            $encrypted['cipher']
        ));
    }
}
