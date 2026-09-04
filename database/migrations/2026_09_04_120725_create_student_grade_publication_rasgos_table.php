<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_grade_publication_rasgos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained('student_grade_publications')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rasgos_score')->nullable();
            $table->timestamps();
            $table->unique(['publication_id', 'student_id'], 'publication_student_rasgos_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_grade_publication_rasgos');
    }
};
