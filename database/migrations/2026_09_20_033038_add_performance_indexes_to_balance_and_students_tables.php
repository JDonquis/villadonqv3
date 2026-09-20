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
        // balance_students: index on status for debt filtering (missing)
        Schema::table('balance_students', function (Blueprint $table) {
            $table->index('status', 'idx_bal_stu_status');
        });

        // students: composite index for filtered lists (status + course + section) (missing)
        Schema::table('students', function (Blueprint $table) {
            $table->index(['status', 'course_id', 'section_id'], 'idx_stu_sta_cou_sec');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('balance_students', function (Blueprint $table) {
            $table->dropIndex('idx_bal_stu_status');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('idx_stu_sta_cou_sec');
        });
    }
};