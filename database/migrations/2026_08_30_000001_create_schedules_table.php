<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_lapse_id')->constrained('school_lapses')->onDelete('cascade');
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->foreignId('section_id')->constrained('sections')->onDelete('cascade');
            $table->time('recess_start')->nullable();
            $table->unsignedInteger('recess_duration_minutes')->nullable();
            $table->timestamps();

            $table->unique(['school_lapse_id', 'course_id', 'section_id'], 'schedule_unique_combo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
