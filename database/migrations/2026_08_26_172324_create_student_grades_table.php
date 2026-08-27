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
        Schema::create('student_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_item_id')->constrained('evaluation_plan_items')->onDelete('cascade')->onUpdate('restrict');
            $table->foreignId('student_id')->constrained()->onDelete('cascade')->onUpdate('restrict');
            $table->decimal('score', 5, 2)->nullable();
            $table->unique(['plan_item_id', 'student_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_grades');
    }
};
