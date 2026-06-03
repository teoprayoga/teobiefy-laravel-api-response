<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse;

use Illuminate\Support\ServiceProvider;
use Teoprayoga\TeobiefyLaravelApiResponse\Console\GenerateEncryptionKeyCommand;
use Teoprayoga\TeobiefyLaravelApiResponse\Contracts\ApiInterface;

class ApiResponseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/api.php', 'api');

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
            ]);
        }

        $this->publishes([
            __DIR__.'/../config/api.php' => config_path('api.php'),
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
