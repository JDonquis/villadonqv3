<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->string('slug', 100)->nullable()->unique()->after('name');
            $table->string('icon', 100)->nullable()->after('slug');
            $table->integer('order')->default(0)->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'icon', 'order']);
        });
    }
};
