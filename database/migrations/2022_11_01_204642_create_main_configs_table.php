<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMainConfigsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('main_configs', function (Blueprint $table) {
            $table->id();
            $table->string("name", 100);
            $table->string("rif", 10);
            $table->string("phone_number", 11);
            $table->string("address", 100);
            $table->string("email", 30);
            $table->date("release");
            $table->string("motto", 100);
            $table->integer("regular_inscription_price");
            $table->integer("new_inscription_price");
            $table->integer("monthly_payment");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('main_configs');
    }
}
