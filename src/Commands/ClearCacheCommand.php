<?php

namespace Aliziodev\IndonesiaRegions\Commands;

use Illuminate\Console\Command;
use Aliziodev\IndonesiaRegions\Facades\Indonesian;

class ClearCacheCommand extends Command
{
    protected $signature = 'indonesian-regions:clear-cache';
    protected $description = 'Clear Indonesia Regions cache';

    public function handle()
    {
        $this->info('Clearing Indonesia Regions cache...');

        if (Indonesian::clearCache()) {
            $this->info('Cache cleared successfully!');
        } else {
            $this->error('Failed to clear cache!');
        }
    }
}