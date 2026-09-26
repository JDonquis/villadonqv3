<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade')->onUpdate('cascade');
            $table->foreignId('school_lapse_id')->constrained()->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('payment_concept_id')->nullable();
            $table->string('type', 50);
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->foreign('payment_concept_id')->references('id')->on('payment_concepts')->nullOnDelete();
            $table->unique(['student_id', 'school_lapse_id', 'type']);
            $table->index(['school_lapse_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_charges');
    }
};
