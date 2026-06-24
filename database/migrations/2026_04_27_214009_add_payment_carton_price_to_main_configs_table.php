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
        Schema::table('main_configs', function (Blueprint $table) {
            $table->decimal('payment_carton_price', 10, 2)->default(0)->after('investment_plan_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('main_configs', function (Blueprint $table) {
            $table->dropColumn('payment_carton_price');
        });
    }
};
