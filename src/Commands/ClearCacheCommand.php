<?php

namespace Aliziodev\IndonesiaRegions\Commands;

use Aliziodev\IndonesiaRegions\Facades\Indonesia;
use Illuminate\Console\Command;

class ClearCacheCommand extends Command
{
    protected $signature = 'indonesia-regions:clear-cache';

    protected $description = 'Clear Indonesia Regions cache';

    public function handle()
    {
        $this->info('Clearing Indonesia Regions cache...');

        if (Indonesia::clearCache()) {
            $this->info('Cache cleared successfully!');
        } else {
            $this->error('Failed to clear cache!');
        }
    }
}
