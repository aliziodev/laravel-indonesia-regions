<?php

namespace Aliziodev\IndonesiaRegions;

use Aliziodev\IndonesiaRegions\Services\IndonesiaRegionService;
use Illuminate\Support\ServiceProvider;

class IndonesiaRegionsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publish migrations from correct package location
        $this->publishes([
            __DIR__.'/Database/Migrations' => database_path('migrations'),
        ], 'indonesia-regions-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\InstallCommand::class,
                Commands\ClearCacheCommand::class,
            ]);
        }
    }

    public function register()
    {
        $this->app->singleton('indonesia-region', function ($app) {
            return new IndonesiaRegionService();
        });
    }
}