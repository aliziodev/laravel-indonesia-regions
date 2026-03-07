<?php

namespace Aliziodev\IndonesiaRegions\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class IndonesiaRegionSeeder extends Seeder
{
    public function run(): void
    {
        $sqlPath = dirname(__DIR__).DIRECTORY_SEPARATOR.'Sql'.DIRECTORY_SEPARATOR.'indonesia_regions.sql';

        if (! File::exists($sqlPath)) {
            $this->command->error('SQL file not found at: '.$sqlPath);

            return;
        }

        $this->command->info('Starting to seed Indonesia regions from SQL...');
        $this->command->info('Indonesia regions seeded successfully!');

        $handle = fopen($sqlPath, 'rb');
        if (! $handle) {
            $this->command->error('Unable to read SQL file.');

            return;
        }

        $statement = '';
        $executed = 0;

        while (($line = fgets($handle)) !== false) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $statement .= $line;

            if (str_ends_with($trimmed, ';')) {
                DB::unprepared($statement);
                $statement = '';
                $executed++;
            }
        }

        fclose($handle);

        if ($statement !== '') {
            DB::unprepared($statement);
            $executed++;
        }

        $this->command->info("Executed {$executed} SQL statement(s).");
    }
}
