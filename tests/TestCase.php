<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Teoprayoga\TeobiefyLaravelApiResponse\ApiResponseServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ApiResponseServiceProvider::class,
        ];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('api.compression.driver', 'zstd');
        $app['config']->set('api.compression.level', 3);
        $app['config']->set('api.compression.min_bytes', 1024);
        $app['config']->set('api.compression.max_decompressed_bytes', 10485760);
        $app['config']->set('api.encryption.driver', 'xchacha20-poly1305');
        $app['config']->set('api.encryption.key', base64_encode(str_repeat('k', 32)));
        $app['config']->set('api.encryption.allow_sodium_compat_fallback', false);
    }
}
