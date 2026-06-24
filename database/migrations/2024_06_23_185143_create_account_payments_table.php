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
        Schema::create('account_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_method_id');
            $table->string('person_name')->nullable();
            $table->string('email')->nullable();
            $table->string('ci')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('bank')->nullable();
            $table->string('account_number')->nullable();
            $table->string('username')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_payments');
    }
};
