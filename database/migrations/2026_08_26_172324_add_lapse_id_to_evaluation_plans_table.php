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
        Schema::table('evaluation_plans', function (Blueprint $table) {
            $table->foreignId('lapse_id')->nullable()->after('school_lapse_id')->constrained('lapses')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluation_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lapse_id');
        });
    }
};
