<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            $table->foreignId('graded_by')
                ->nullable()
                ->after('score')
                ->constrained('users')
                ->nullOnDelete()
                ->onUpdate('restrict');
        });

        Schema::table('student_plan_rasgos', function (Blueprint $table) {
            $table->foreignId('graded_by')
                ->nullable()
                ->after('rasgos_score')
                ->constrained('users')
                ->nullOnDelete()
                ->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            $table->dropConstrainedForeignId('graded_by');
        });

        Schema::table('student_plan_rasgos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('graded_by');
        });
    }
};
