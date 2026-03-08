<?php

namespace Aliziodev\IndonesiaRegions\Tests;

use Aliziodev\IndonesiaRegions\IndonesiaRegionsServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            IndonesiaRegionsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('indonesia-regions.cache.store', 'array');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../src/Database/Migrations');
    }

    protected function seedRegions(): void
    {
        \DB::table('indonesia_regions')->insert([
            ['code' => '11', 'name' => 'ACEH', 'postal_code' => null, 'status' => 'active', 'search_text' => null],
            ['code' => '11.01', 'name' => 'KAB. ACEH SELATAN', 'postal_code' => null, 'status' => 'active', 'search_text' => null],
            ['code' => '11.01.01', 'name' => 'BAKONGAN', 'postal_code' => null, 'status' => 'active', 'search_text' => null],
            ['code' => '11.01.01.2001', 'name' => 'KEUDE BAKONGAN', 'postal_code' => '23773', 'status' => 'active', 'search_text' => 'KEUDE BAKONGAN BAKONGAN KAB ACEH SELATAN ACEH 23773'],
        ]);
    }
}
