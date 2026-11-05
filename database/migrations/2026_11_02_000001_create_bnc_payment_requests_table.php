<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bnc_payment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('account_payment_id')->constrained('account_payments')->restrictOnDelete();
            $table->string('type', 20)->default('regular');
            $table->string('reference', 50)->nullable();
            $table->string('reference_normalized', 20)->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('amount_usd', 10, 2)->default(0);
            $table->decimal('amount_ves', 12, 2)->default(0);
            $table->string('currency', 3)->default('VES');
            $table->date('transaction_date');
            $table->date('reported_date')->nullable();
            $table->json('payload');
            $table->json('allowed_student_ids')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->json('payment_ids')->nullable();
            $table->string('verification_source', 20)->nullable();
            $table->json('verification_response')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['account_payment_id', 'reference']);
            $table->index('reference_normalized');
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bnc_payment_requests');
    }
};
