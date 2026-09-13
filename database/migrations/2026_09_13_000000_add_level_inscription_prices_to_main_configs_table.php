<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('main_configs', function (Blueprint $table) {
            $table->integer('preescolar_inscription_price')->nullable()->after('new_inscription_price');
            $table->integer('primaria_inscription_price')->nullable()->after('preescolar_inscription_price');
            $table->integer('secundaria_inscription_price')->nullable()->after('primaria_inscription_price');
        });

        // Los precios existentes quedan como base para todos los niveles.
        DB::table('main_configs')->update([
            'preescolar_inscription_price' => DB::raw('new_inscription_price'),
            'primaria_inscription_price' => DB::raw('new_inscription_price'),
            'secundaria_inscription_price' => DB::raw('new_inscription_price'),
        ]);
    }

    public function down(): void
    {
        Schema::table('main_configs', function (Blueprint $table) {
            $table->dropColumn([
                'preescolar_inscription_price',
                'primaria_inscription_price',
                'secundaria_inscription_price',
            ]);
        });
    }
};
