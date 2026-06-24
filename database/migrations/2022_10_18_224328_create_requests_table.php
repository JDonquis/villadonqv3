<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
       public function up()
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId("request_statu_id")->constrained()->onDelete("restrict")->onUpdate("restrict")->default('3');
            $table->tinyInteger('year');
            $table->string("name",30);
            $table->string("last_name",30);
            $table->string("DNI",30);
            $table->string("age",2);
            $table->string("email",30);
            $table->string("phone_number",11);
            $table->date("date_birth");
            $table->string('state',20);
            $table->string('city',20);
             $table->string("address",100);
            $table->string("rep_name",30);
            $table->string("rep_last_name",30);
            $table->string("rep_DNI",30);
            $table->string("rep_phone_number",11);
            $table->string("rep_email",30);   
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
        Schema::dropIfExists('requests');
    }
}
