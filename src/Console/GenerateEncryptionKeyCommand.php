<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Console;

use Illuminate\Console\Command;

class GenerateEncryptionKeyCommand extends Command
{
    protected $signature = 'teobiefy:key';

    protected $description = 'Generate a TEOBIEFY_ENCRYPTION_KEY value.';

    public function handle(): int
    {
        $this->line('TEOBIEFY_ENCRYPTION_KEY=base64:'.base64_encode(random_bytes(32)));

        return self::SUCCESS;
    }
}
