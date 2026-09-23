<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('main_configs', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->after('motto');
            $table->string('municipality', 100)->nullable()->after('code');
            $table->string('federal_entity', 100)->nullable()->after('municipality');
            $table->string('cdcee', 20)->nullable()->after('federal_entity');
            $table->string('director_name', 100)->nullable()->after('cdcee');
            $table->string('director_ci', 20)->nullable()->after('director_name');
            $table->decimal('latitude', 10, 8)->nullable()->after('director_ci');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('main_configs', function (Blueprint $table) {
            $table->dropColumn([
                'code',
                'municipality',
                'federal_entity',
                'cdcee',
                'director_name',
                'director_ci',
                'latitude',
                'longitude',
            ]);
        });
    }
};