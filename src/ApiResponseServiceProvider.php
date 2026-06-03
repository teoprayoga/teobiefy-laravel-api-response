<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse;

use Illuminate\Support\ServiceProvider;
use Teoprayoga\TeobiefyLaravelApiResponse\Console\GenerateEncryptionKeyCommand;
use Teoprayoga\TeobiefyLaravelApiResponse\Console\GenerateSigningKeyCommand;
use Teoprayoga\TeobiefyLaravelApiResponse\Contracts\ApiInterface;
use Teoprayoga\TeobiefyLaravelApiResponse\Signing\PayloadSigner;

class ApiResponseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/teobiefy.php', 'teobiefy');

        $this->app->singleton(AttributeProfileReader::class);
        $this->app->singleton(RouteProfileResolver::class);
        $this->app->singleton(PayloadSigner::class);

        $this->app->singleton(ApiInterface::class, function () {
            return new ApiResponse(
                $this->app->make(PayloadTransformer::class),
                $this->app->make(RouteProfileResolver::class),
            );
        });
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'api-response');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateEncryptionKeyCommand::class,
                GenerateSigningKeyCommand::class,
            ]);
        }

        $this->publishes([
            __DIR__.'/../config/teobiefy.php' => config_path('teobiefy.php'),
        ], 'api-response');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/api-response'),
        ], 'api-response-lang');

        $this->registerHelpers();
    }

    private function registerHelpers(): void
    {
        $helperFile = __DIR__.'/helpers.php';

        if (file_exists($helperFile)) {
            require_once $helperFile;
        }
    }
}
