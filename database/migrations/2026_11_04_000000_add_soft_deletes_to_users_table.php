<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        DB::table('users')
            ->whereNotNull('deleted_at')
            ->orderBy('id')
            ->eachById(function ($user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'email' => 'deleted+'.$user->id.'@deleted.local',
                        'ci' => 'del'.$user->id.substr(sha1((string) $user->ci), 0, 6),
                        'provider' => null,
                        'provider_id' => null,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
