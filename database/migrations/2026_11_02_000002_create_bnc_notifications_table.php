<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bnc_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('hash', 64)->unique();
            $table->string('payment_type', 10)->nullable();
            $table->string('origin_bank_reference', 100)->nullable();
            $table->string('destiny_bank_reference', 100)->nullable();
            $table->string('origin_bank_code', 10)->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency_code', 10)->nullable();
            $table->date('transaction_date')->nullable();
            $table->string('transaction_hour', 10)->nullable();
            $table->string('commerce_id', 30)->nullable();
            $table->string('client_id', 30)->nullable();
            $table->string('client_phone', 30)->nullable();
            $table->string('debtor_account', 30)->nullable();
            $table->string('debtor_id', 30)->nullable();
            $table->string('creditor_account', 30)->nullable();
            $table->text('concept')->nullable();
            $table->json('raw_payload');
            $table->string('status', 20)->default('received');
            $table->foreignId('bnc_payment_request_id')->nullable()->constrained('bnc_payment_requests')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('origin_bank_reference');
            $table->index('transaction_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bnc_notifications');
    }
};
