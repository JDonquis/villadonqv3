<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBalanceStudentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('balance_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id');
            $table->float('inscription');
            $table->float('january');
            $table->float('february');
            $table->float('march');
            $table->float('april');
            $table->float('may');
            $table->float('june');
            $table->float('july');
            $table->float('august');
            $table->float('september');
            $table->float('october');
            $table->float('november');
            $table->float('december');
            $table->foreignId('school_lapse_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('balance_students');
    }
}
