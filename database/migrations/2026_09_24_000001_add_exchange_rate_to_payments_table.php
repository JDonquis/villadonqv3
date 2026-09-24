<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'exchange_rate')) {
                $table->decimal('exchange_rate', 12, 2)->nullable()->after('total_in_bs');
            }
        });

        DB::table('payments')
            ->whereNotNull('total_in_bs')
            ->where('total_in_bs', '>', 0)
            ->whereNotNull('total_in_dolars')
            ->where('total_in_dolars', '>', 0)
            ->update([
                'exchange_rate' => DB::raw('ROUND(total_in_bs / total_in_dolars, 2)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'exchange_rate')) {
                $table->dropColumn('exchange_rate');
            }
        });
    }
};
