<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Tests\Fixtures;

use Teoprayoga\TeobiefyLaravelApiResponse\Attributes\ResponseProfile;
use Teoprayoga\TeobiefyLaravelApiResponse\Profile;

#[ResponseProfile(Profile::ENCRYPTED)]
class InvokableController
{
    public function __invoke(): string
    {
        return 'ok';
    }
}
