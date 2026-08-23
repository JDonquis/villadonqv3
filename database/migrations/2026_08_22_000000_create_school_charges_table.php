<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('school_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('restrict')->onUpdate('restrict');
            $table->foreignId('school_lapse_id')->constrained()->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('amount', 8, 2)->default(1.00);
            $table->timestamps();

            $table->unique(['student_id', 'school_lapse_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('school_charges');
    }
};
