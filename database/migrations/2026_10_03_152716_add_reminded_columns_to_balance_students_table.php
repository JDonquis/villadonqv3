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
        Schema::table('balance_students', function (Blueprint $table) {
            $months = [
                'september', 'october', 'november', 'december',
                'january', 'february', 'march', 'april',
                'may', 'june', 'july', 'august',
            ];

            foreach ($months as $month) {
                $table->boolean($month.'_reminded')->default(false)->after($month.'_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('balance_students', function (Blueprint $table) {
            $months = [
                'september', 'october', 'november', 'december',
                'january', 'february', 'march', 'april',
                'may', 'june', 'july', 'august',
            ];

            foreach ($months as $month) {
                $table->dropColumn($month.'_reminded');
            }
        });
    }
};
