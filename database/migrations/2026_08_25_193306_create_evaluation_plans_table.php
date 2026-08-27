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
        Schema::create('evaluation_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->onUpdate('restrict');
            $table->foreignId('matter_id')->constrained()->onDelete('restrict')->onUpdate('restrict');
            $table->foreignId('school_lapse_id')->constrained()->onDelete('restrict')->onUpdate('restrict');
            $table->foreignId('course_id')->nullable()->constrained()->onDelete('restrict')->onUpdate('restrict');
            $table->foreignId('section_id')->nullable()->constrained()->onDelete('restrict')->onUpdate('restrict');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->text('admin_note')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null')->onUpdate('restrict');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_plans');
    }
};
