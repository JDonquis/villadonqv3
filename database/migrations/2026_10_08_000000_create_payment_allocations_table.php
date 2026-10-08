<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->onDelete('cascade')->onUpdate('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade')->onUpdate('cascade');
            $table->foreignId('payment_concept_id')->nullable()->constrained('payment_concepts')->nullOnDelete()->onUpdate('cascade');
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->index('payment_id');
            $table->index('student_id');
            $table->index('payment_concept_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
