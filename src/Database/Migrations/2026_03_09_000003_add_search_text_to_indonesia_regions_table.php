<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('indonesia_regions')) {
            return;
        }

        if (! Schema::hasColumn('indonesia_regions', 'search_text')) {
            Schema::table('indonesia_regions', function (Blueprint $table): void {
                $table->text('search_text')->nullable()->after('status');
            });
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE indonesia_regions ADD FULLTEXT INDEX idx_region_search_text (search_text)');
        }

        if ($driver === 'pgsql') {
            DB::statement("CREATE INDEX IF NOT EXISTS idx_region_search_text ON indonesia_regions USING GIN (to_tsvector('simple', COALESCE(search_text, '')))");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('indonesia_regions')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            try {
                DB::statement('ALTER TABLE indonesia_regions DROP INDEX idx_region_search_text');
            } catch (Throwable) {
            }
        }

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_region_search_text');
        }

        if (Schema::hasColumn('indonesia_regions', 'search_text')) {
            Schema::table('indonesia_regions', function (Blueprint $table): void {
                $table->dropColumn('search_text');
            });
        }
    }
};
