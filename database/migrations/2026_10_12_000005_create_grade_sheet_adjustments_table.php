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
        Schema::create('grade_sheet_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_sheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('points')->default(0);
            $table->timestamps();

            $table->unique(['grade_sheet_id', 'student_id', 'matter_id'], 'grade_sheet_adjustment_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_sheet_adjustments');
    }
};
