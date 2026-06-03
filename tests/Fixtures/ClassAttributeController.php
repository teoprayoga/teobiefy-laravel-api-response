<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Tests\Fixtures;

use Teoprayoga\TeobiefyLaravelApiResponse\Attributes\RequestProfile;
use Teoprayoga\TeobiefyLaravelApiResponse\Profile;

#[RequestProfile(Profile::ENCRYPTED)]
class ClassAttributeController
{
    public function store(): string
    {
        return 'ok';
    }
}
