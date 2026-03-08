<?php

namespace Aliziodev\IndonesiaRegions;

use Aliziodev\IndonesiaRegions\Contracts\ApiResponderInterface;
use Aliziodev\IndonesiaRegions\Contracts\IndonesiaRegionInterface;
use Aliziodev\IndonesiaRegions\Services\IndonesiaRegionService;
use Aliziodev\IndonesiaRegions\Support\JsonApiResponder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class IndonesiaRegionsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if (config('indonesia-regions.api.enabled', true)) {
            Route::middleware(config('indonesia-regions.api.middleware', ['api']))
                ->prefix(config('indonesia-regions.api.prefix', 'api/indonesia-regions'))
                ->group(__DIR__.'/../routes/api.php');
        }

        $this->publishes([
            __DIR__.'/Database/Migrations' => database_path('migrations'),
        ], 'indonesia-regions-migrations');

        $this->publishes([
            __DIR__.'/../config/indonesia-regions.php' => config_path('indonesia-regions.php'),
        ], 'indonesia-regions-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\InstallCommand::class,
                Commands\SyncCommand::class,
                Commands\ClearCacheCommand::class,
            ]);
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/indonesia-regions.php', 'indonesia-regions');

        $this->app->singleton(ApiResponderInterface::class, function ($app) {
            $responder = config('indonesia-regions.api.responder');

            if (is_string($responder)) {
                if (! class_exists($responder)) {
                    throw new InvalidArgumentException("Configured API responder class not found: {$responder}");
                }

                if (! is_a($responder, ApiResponderInterface::class, true)) {
                    throw new InvalidArgumentException('Configured API responder must implement '.ApiResponderInterface::class);
                }

                return $app->make($responder);
            }

            return new JsonApiResponder;
        });

        $this->app->singleton(IndonesiaRegionInterface::class, function ($app) {
            return new IndonesiaRegionService;
        });

        $this->app->singleton('indonesia-region', function ($app) {
            return $app->make(IndonesiaRegionInterface::class);
        });
    }
}
