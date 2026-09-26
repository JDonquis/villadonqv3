<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_charge_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_charge_id')->constrained()->onDelete('cascade')->onUpdate('cascade');
            $table->foreignId('payment_id')->constrained()->onDelete('cascade')->onUpdate('cascade');
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->index('payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_charge_payments');
    }
};
