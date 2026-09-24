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
        Schema::create('evaluation_plan_attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_plan_id')
                ->constrained('evaluation_plans')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->date('date');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['evaluation_plan_id', 'date'], 'eps_plan_date_idx');
            $table->unique(['evaluation_plan_id', 'date'], 'eps_plan_date_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_plan_attendance_sessions');
    }
};
