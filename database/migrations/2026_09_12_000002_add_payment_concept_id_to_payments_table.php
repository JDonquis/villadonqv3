<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_concept_id')->nullable()->after('account_payment_id');
            $table->foreign('payment_concept_id')->references('id')->on('payment_concepts')->nullOnDelete();
            $table->index('payment_concept_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['payment_concept_id']);
            $table->dropIndex(['payment_concept_id']);
            $table->dropColumn('payment_concept_id');
        });
    }
};