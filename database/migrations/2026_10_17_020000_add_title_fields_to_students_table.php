<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('title_serial', 30)->nullable()->unique()->after('graduate');
            $table->date('title_issued_at')->nullable()->after('title_serial');
            $table->string('title_observations', 255)->nullable()->after('title_issued_at');
        });

        $exists = DB::table('modules')->where('slug', 'documentos')->exists();

        if (! $exists) {
            DB::table('modules')->insert([
                'name' => 'Documentos',
                'slug' => 'documentos',
                'icon' => 'mdi:file-document-multiple-outline',
                'order' => 12,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('modules')->where('slug', 'documentos')->delete();

        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['title_serial']);
            $table->dropColumn(['title_serial', 'title_issued_at', 'title_observations']);
        });
    }
};
