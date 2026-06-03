<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Tests\Fixtures;

use Teoprayoga\TeobiefyLaravelApiResponse\Attributes\ResponseProfile;
use Teoprayoga\TeobiefyLaravelApiResponse\Profile;

class MethodAttributeController
{
    #[ResponseProfile(Profile::ENCRYPTED)]
    public function show(): string
    {
        return 'ok';
    }
}
