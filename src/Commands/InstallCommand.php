<?php

namespace Aliziodev\IndonesiaRegions\Commands;

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

        $this->call('indonesia-regions:sync', [
            '--force' => true,
        ]);

        $this->info('Indonesia Regions installed successfully!');
    }
}
