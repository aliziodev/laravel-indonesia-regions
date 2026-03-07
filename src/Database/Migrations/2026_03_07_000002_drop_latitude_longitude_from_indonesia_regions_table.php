<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('indonesia_regions')) {
            return;
        }

        $hasLatitude = Schema::hasColumn('indonesia_regions', 'latitude');
        $hasLongitude = Schema::hasColumn('indonesia_regions', 'longitude');

        if (! $hasLatitude && ! $hasLongitude) {
            return;
        }

        Schema::table('indonesia_regions', function (Blueprint $table) use ($hasLatitude, $hasLongitude): void {
            if ($hasLatitude) {
                $table->dropColumn('latitude');
            }

            if ($hasLongitude) {
                $table->dropColumn('longitude');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('indonesia_regions')) {
            return;
        }

        Schema::table('indonesia_regions', function (Blueprint $table): void {
            if (! Schema::hasColumn('indonesia_regions', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable();
            }

            if (! Schema::hasColumn('indonesia_regions', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable();
            }
        });
    }
};
