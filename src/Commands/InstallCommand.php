<?php

namespace Aliziodev\IndonesiaRegions\Commands;

use Illuminate\Console\Command;
use Aliziodev\IndonesiaRegions\Database\Seeders\IndonesiaRegionSeeder;

class InstallCommand extends Command
{
    protected $signature = 'indonesia-regions:install';
    protected $description = 'Install Indonesia Regions package';

    public function handle()
    {
        $this->info('Installing Indonesia Regions...');

        // Publish migrations
        $this->call('vendor:publish', [
            '--tag' => 'indonesia-regions-migrations'
        ]);

        // Run migrations
        $this->call('migrate');

        // Run seeder directly from package
        $seeder = new IndonesiaRegionSeeder();
        $seeder->setCommand($this);
        $seeder->run();

        $this->info('Indonesia Regions installed successfully!');
    }
}