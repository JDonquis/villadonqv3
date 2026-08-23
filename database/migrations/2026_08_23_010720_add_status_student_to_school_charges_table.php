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
        Schema::table('school_charges', function (Blueprint $table) {
            $table->enum('status', ['pending', 'paid', 'overdue'])->default('pending')->after('amount');
            $table->string('student_status', 20)->default(1)->after('status'); // 1 -> Active, 0 -> Inactive
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_charges', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->dropColumn('student_status');
        });
    }
};
