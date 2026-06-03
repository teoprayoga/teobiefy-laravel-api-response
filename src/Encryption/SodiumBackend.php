<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Encryption;

interface SodiumBackend
{
    public function encrypt(string $driver, string $payload, string $nonce, string $key): string;

    public function decrypt(string $driver, string $payload, string $nonce, string $key): string|false;
}
