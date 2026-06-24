<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('balance_students', function (Blueprint $table) {
            $table->string('january_status')->default('pending')->after('january');
            $table->string('february_status')->default('pending')->after('february');
            $table->string('march_status')->default('pending')->after('march');
            $table->string('april_status')->default('pending')->after('april');
            $table->string('may_status')->default('pending')->after('may');
            $table->string('june_status')->default('pending')->after('june');
            $table->string('july_status')->default('pending')->after('july');
            $table->string('august_status')->default('pending')->after('august');
            $table->string('september_status')->default('pending')->after('september');
            $table->string('october_status')->default('pending')->after('october');
            $table->string('november_status')->default('pending')->after('november');
            $table->string('december_status')->default('pending')->after('december');
        });
    }

    public function down(): void
    {
        Schema::table('balance_students', function (Blueprint $table) {
            $table->dropColumn([
                'january_status',
                'february_status',
                'march_status',
                'april_status',
                'may_status',
                'june_status',
                'july_status',
                'august_status',
                'september_status',
                'october_status',
                'november_status',
                'december_status',
            ]);
        });
    }
};
