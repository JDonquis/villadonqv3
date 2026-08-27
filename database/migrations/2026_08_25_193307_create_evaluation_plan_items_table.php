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
        Schema::create('evaluation_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_plan_id')->constrained()->onDelete('cascade')->onUpdate('restrict');
            $table->string('name');
            $table->decimal('percentage', 5, 2);
            $table->date('date')->nullable();
            $table->integer('order')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_plan_items');
    }
};
