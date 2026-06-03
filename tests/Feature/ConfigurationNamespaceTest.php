<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Tests\Feature;

use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Teoprayoga\TeobiefyLaravelApiResponse\ApiResponseServiceProvider;

class ConfigurationNamespaceTest extends Orchestra
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

    public function test_package_merges_configuration_under_teobiefy_namespace_only(): void
    {
        $this->assertTrue(config()->has('teobiefy.stringify'));
        $this->assertFalse(config()->has('api.stringify'));
    }

    public function test_api_response_publish_tag_publishes_teobiefy_config_file(): void
    {
        $paths = ServiceProvider::pathsToPublish(ApiResponseServiceProvider::class, 'api-response');

        $this->assertCount(1, $paths);

        $source = array_key_first($paths);

        $this->assertSame(realpath(__DIR__.'/../../config/teobiefy.php'), realpath($source));
        $this->assertSame(config_path('teobiefy.php'), $paths[$source]);
    }
}
