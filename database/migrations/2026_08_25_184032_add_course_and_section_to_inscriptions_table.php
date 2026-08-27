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
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable()->after('school_lapse_id')->constrained()->onDelete('restrict')->onUpdate('restrict');
            $table->foreignId('section_id')->nullable()->after('course_id')->constrained()->onDelete('restrict')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('course_id');
            $table->dropConstrainedForeignId('section_id');
        });
    }
};
