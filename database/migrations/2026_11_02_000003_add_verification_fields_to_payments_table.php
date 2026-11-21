<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('verification_status', 20)->default('not_required')->after('status');
            $table->string('verification_source', 20)->nullable()->after('verification_status');
            $table->timestamp('verified_at')->nullable()->after('verification_source');
            $table->foreignId('verified_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            $table->string('verification_reference', 100)->nullable()->after('verified_by');
            $table->json('verification_response')->nullable()->after('verification_reference');

            $table->index('verification_status');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['verification_status']);
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn([
                'verification_status',
                'verification_source',
                'verified_at',
                'verification_reference',
                'verification_response',
            ]);
        });
    }
};
