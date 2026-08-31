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
        Schema::table('evaluation_plan_items', function (Blueprint $table) {
            $table->string('unit_name')->nullable()->after('evaluation_plan_id');
            $table->unsignedInteger('unit_number')->nullable()->after('unit_name');
            $table->string('assessment_type')->nullable()->after('name');
            $table->decimal('points', 8, 2)->nullable()->after('percentage');
            $table->date('scheduled_date')->nullable()->after('date');
            $table->text('description')->nullable()->after('scheduled_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluation_plan_items', function (Blueprint $table) {
            $table->dropColumn([
                'unit_name',
                'unit_number',
                'assessment_type',
                'points',
                'scheduled_date',
                'description',
            ]);
        });
    }
};
