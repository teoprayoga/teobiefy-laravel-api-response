<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Tests\Fixtures;

use Teoprayoga\TeobiefyLaravelApiResponse\Attributes\ResponseProfile;
use Teoprayoga\TeobiefyLaravelApiResponse\Profile;

#[ResponseProfile(Profile::COMPRESSED)]
class OverrideController
{
    #[ResponseProfile(Profile::ENCRYPTED)]
    public function show(): string
    {
        return 'ok';
    }
}
