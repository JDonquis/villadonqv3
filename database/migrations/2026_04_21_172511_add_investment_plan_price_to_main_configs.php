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
            $table->decimal('investment_plan_price', 8, 2)->nullable()->after('ame_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('main_configs', function (Blueprint $table) {
            $table->dropColumn('investment_plan_price');
        });
    }
};
