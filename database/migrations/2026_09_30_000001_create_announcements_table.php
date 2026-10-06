<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();

            $table->string('title', 150);
            $table->text('body');

            // Audiencia: all | course | section | student
            $table->string('audience', 20)->default('all');
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('section_id')->nullable()->constrained('sections')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete()->cascadeOnUpdate();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();

            $table->timestamp('published_at')->nullable();
            $table->tinyInteger('status')->default(1);

            $table->timestamps();

            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
