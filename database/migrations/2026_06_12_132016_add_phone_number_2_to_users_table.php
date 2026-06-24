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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_number2', 30)->nullable()->after('phone_number');
            $table->string('state', 50)->nullable()->after('address');
            $table->string('city', 50)->nullable()->after('state');
        });

        Schema::table('representatives', function (Blueprint $table) {
            $table->string('second_representative_phone_number2', 30)->nullable()->after('second_representative_phone_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_number2', 'state', 'city']);
        });

        Schema::table('representatives', function (Blueprint $table) {
            $table->dropColumn('second_representative_phone_number2');
        });
    }
};
