<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('account_payment_id');
            $table->date('date');
            $table->decimal('total_in_dolars', 10, 2)->default(0);
            $table->decimal('total_in_bs', 12, 2)->default(0);
            $table->string('reference', 50)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('account_payment_id')->references('id')->on('account_payments')->onDelete('cascade');

            $table->index('account_payment_id');
            $table->index('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
