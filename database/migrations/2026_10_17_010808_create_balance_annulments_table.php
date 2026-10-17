<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_annulments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_lapse_id')->nullable()->constrained('school_lapses')->nullOnDelete();
            $table->string('up_to_month');
            $table->boolean('include_inscription')->default(false);
            $table->json('filters')->nullable();
            $table->json('snapshot')->nullable();
            $table->unsignedInteger('affected_count')->default(0);
            $table->decimal('total_annulled', 12, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_annulments');
    }
};
