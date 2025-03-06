<?php

namespace Aliziodev\IndonesiaRegions\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class IndonesiaRegionSeeder extends Seeder
{
    public function run(): void
    {
        // Define the path to the SQL file
        $sqlPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Sql' . DIRECTORY_SEPARATOR . 'indonesia_regions.sql';

        // Check if the file exists
        if (!File::exists($sqlPath)) {
            $this->command->error('SQL file not found at: ' . $sqlPath);
            return;
        }

        // Output start message
        $this->command->info('Starting to seed Indonesia regions from SQL...');

        // Read the SQL file
        $sql = File::get($sqlPath);

        // Initialize the progress bar
        $this->command->getOutput()->progressStart(1);

        // Execute the SQL statements
        DB::unprepared($sql);

        // Update the progress bar
        $this->command->getOutput()->progressAdvance();

        // Finish the progress bar
        $this->command->getOutput()->progressFinish();

        // Output success message
        $this->command->info('Indonesia regions seeded successfully!');
    }
}
