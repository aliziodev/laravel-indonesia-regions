<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateIndonesiaRegionsTable extends Migration
{
    public function up()
    {
        Schema::create('indonesia_regions', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('name');
            $table->string('postal_code')->nullable();
            $table->string('status')->nullable();

            $table->index('name');
            $table->index('postal_code');
        });

        $driver = DB::getDriverName();

        switch ($driver) {
            case 'mysql':
                DB::statement('CREATE INDEX idx_region_code_length ON indonesia_regions((LENGTH(code)))');
                DB::statement('CREATE INDEX idx_region_code_province ON indonesia_regions((LEFT(code, 2)))');
                DB::statement('CREATE INDEX idx_region_code_city ON indonesia_regions((LEFT(code, 5)))');
                DB::statement('CREATE INDEX idx_region_code_district ON indonesia_regions((LEFT(code, 8)))');
                break;

            case 'pgsql':
                DB::statement('CREATE INDEX idx_region_code_length ON indonesia_regions (LENGTH(code))');
                DB::statement('CREATE INDEX idx_region_code_province ON indonesia_regions (SUBSTRING(code FROM 1 FOR 2))');
                DB::statement('CREATE INDEX idx_region_code_city ON indonesia_regions (SUBSTRING(code FROM 1 FOR 5))');
                DB::statement('CREATE INDEX idx_region_code_district ON indonesia_regions (SUBSTRING(code FROM 1 FOR 8))');
                break;

            case 'sqlite':
                break;

            case 'sqlsrv':
                DB::statement('CREATE INDEX idx_region_code_length ON indonesia_regions (LEN(code))');
                DB::statement('CREATE INDEX idx_region_code_province ON indonesia_regions (LEFT(code, 2))');
                DB::statement('CREATE INDEX idx_region_code_city ON indonesia_regions (LEFT(code, 5))');
                DB::statement('CREATE INDEX idx_region_code_district ON indonesia_regions (LEFT(code, 8))');
                break;
        }
    }

    public function down()
    {
        Schema::dropIfExists('indonesia_regions');
    }
}
