<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_plan_rasgos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rasgos_score')->nullable();
            $table->timestamps();
            $table->unique(['evaluation_plan_id', 'student_id'], 'student_plan_rasgos_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_plan_rasgos');
    }
};
