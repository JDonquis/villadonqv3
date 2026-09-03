<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_grade_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->timestamp('published_at');
            $table->timestamps();
            $table->unique(['evaluation_plan_id', 'version']);
        });

        Schema::create('student_grade_publication_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained('student_grade_publications')->cascadeOnDelete();
            $table->foreignId('plan_item_id')->constrained('evaluation_plan_items')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['publication_id', 'plan_item_id', 'student_id'], 'publication_grade_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_grade_publication_items');
        Schema::dropIfExists('student_grade_publications');
    }
};