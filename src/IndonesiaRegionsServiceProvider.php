<?php

namespace Aliziodev\IndonesiaRegions;

use Aliziodev\IndonesiaRegions\Services\IndonesiaRegionService;
use Illuminate\Support\ServiceProvider;

class IndonesiaRegionsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/Database/Migrations' => database_path('migrations'),
        ], 'indonesia-regions-migrations');

        $this->publishes([
            __DIR__.'/../config/indonesia-regions.php' => config_path('indonesia-regions.php'),
        ], 'indonesia-regions-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\InstallCommand::class,
                Commands\ClearCacheCommand::class,
            ]);
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/indonesia-regions.php', 'indonesia-regions');

        $this->app->singleton('indonesia-region', function ($app) {
            return new IndonesiaRegionService;
        });
    }
}
