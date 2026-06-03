<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Facades;

use Illuminate\Support\Facades\Facade;
use Teoprayoga\TeobiefyLaravelApiResponse\Contracts\ApiInterface;

class API extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ApiInterface::class;
    }
}
