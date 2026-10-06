<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_events', function (Blueprint $table) {
            $table->id();

            $table->string('title', 150);
            $table->text('description')->nullable();

            // exam | meeting | holiday | general
            $table->string('type', 30)->default('general');

            $table->date('start_date');
            $table->date('end_date')->nullable();

            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('section_id')->nullable()->constrained('sections')->nullOnDelete()->cascadeOnUpdate();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();

            $table->timestamp('published_at')->nullable();
            $table->tinyInteger('status')->default(1);

            $table->timestamps();

            $table->index(['status', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_events');
    }
};
