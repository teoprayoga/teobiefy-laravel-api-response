<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Console;

use Illuminate\Console\Command;

class GenerateSigningKeyCommand extends Command
{
    protected $signature = 'teobiefy:signing-key';

    protected $description = 'Generate a TEOBIEFY_SIGNING_KEY value.';

    public function handle(): int
    {
        $this->line('TEOBIEFY_SIGNING_KEY=base64:'.base64_encode(random_bytes(32)));

        return self::SUCCESS;
    }
}
