<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('balance_students', function (Blueprint $table) {
            $table->string('inscription_status')->default('pending')->after('inscription');
        });
    }

    public function down(): void
    {
        Schema::table('balance_students', function (Blueprint $table) {
            $table->dropColumn('inscription_status');
        });
    }
};
