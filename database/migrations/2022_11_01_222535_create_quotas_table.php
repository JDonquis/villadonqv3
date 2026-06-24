<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuotasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('quotas', function (Blueprint $table) {
            $table->id();
            $table->integer('assigned');
            $table->integer('accepted');
            $table->integer('remaining');
            $table->foreignId("course_id")->constrained()->onDelete("restrict")->onUpdate("restrict");
            $table->foreignId("school_lapse_id")->constrained()->onDelete("restrict")->onUpdate("restrict");
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
        Schema::dropIfExists('quotas');
    }
}
