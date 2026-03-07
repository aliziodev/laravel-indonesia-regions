<?php

namespace Aliziodev\IndonesiaRegions\Commands;

use Aliziodev\IndonesiaRegions\Database\Seeders\IndonesiaRegionSeeder;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'indonesia-regions:install';

    protected $description = 'Install Indonesia Regions package';

    public function handle()
    {
        $this->info('Installing Indonesia Regions...');

        $this->call('vendor:publish', [
            '--tag' => 'indonesia-regions-migrations',
        ]);

        $this->call('migrate');

        $this->call('db:seed', [
            '--class' => IndonesiaRegionSeeder::class,
            '--force' => true,
        ]);

        $this->info('Indonesia Regions installed successfully!');
    }
}
