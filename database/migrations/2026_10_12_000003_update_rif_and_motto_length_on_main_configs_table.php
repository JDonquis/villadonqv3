<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE main_configs MODIFY rif VARCHAR(20) NULL, MODIFY motto VARCHAR(255) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE main_configs MODIFY rif VARCHAR(10) NOT NULL, MODIFY motto VARCHAR(100) NOT NULL');
    }
};
