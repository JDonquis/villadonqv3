<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLapsesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lapses', function (Blueprint $table) {
            $table->id();
            $table->date('start');
            $table->date('end');
            $table->tinyInteger('number');
            $table->foreignId('school_lapse_id')->constrained()->onDelete("cascade")->onUpdate("restrict");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lapses');
    }
}
