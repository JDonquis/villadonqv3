<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRepresentativesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('representatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id")->constrained()->onDelete("cascade")->onUpdate("restrict");
            $table->string("profession", 100)->nullable();
            $table->string("workplace", 100)->nullable();
            $table->string('relationship', 100)->nullable();
            $table->string('second_representative_relationship', 100)->nullable();
            $table->string("second_representative_name", 100)->nullable();
            $table->string("second_representative_last_name", 100)->nullable();
            $table->string("second_representative_ci", 100)->nullable();
            $table->string("second_representative_phone_number", 100)->nullable();
            $table->string("second_representative_email", 100)->nullable();
            $table->string("second_representative_profession", 100)->nullable();
            $table->string("second_representative_workplace", 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('representatives');
    }
}
