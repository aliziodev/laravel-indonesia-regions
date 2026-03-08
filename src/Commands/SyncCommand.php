<?php

namespace Aliziodev\IndonesiaRegions\Commands;

use Aliziodev\IndonesiaRegions\Database\Seeders\IndonesiaRegionSeeder;
use Aliziodev\IndonesiaRegions\Facades\Indonesia;
use Illuminate\Console\Command;

class SyncCommand extends Command
{
    protected $signature = 'indonesia-regions:sync {--force : Run sync in production}';

    protected $description = 'Sync Indonesia Regions dataset into the database';

    public function handle(): int
    {
        $this->info('Syncing Indonesia Regions dataset...');

        $parameters = [
            '--class' => IndonesiaRegionSeeder::class,
            '--force' => (bool) $this->option('force'),
        ];

        if ($connection = config('indonesia-regions.database.connection')) {
            $parameters['--database'] = $connection;
        }

        $seedStatus = $this->call('db:seed', $parameters);

        if ($seedStatus !== self::SUCCESS) {
            $this->error('Indonesia Regions sync failed during database seeding.');

            return $seedStatus;
        }

        if (! $this->clearPackageCache()) {
            $this->error('Indonesia Regions synced, but clearing cache failed.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function clearPackageCache(): bool
    {
        return Indonesia::clearCache();
    }
}
