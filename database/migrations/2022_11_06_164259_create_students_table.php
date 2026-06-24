<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStudentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId("representative_id")->constrained()->onDelete("cascade")->onUpdate("restrict");
            $table->foreignId("course_id")->constrained()->onDelete("restrict")->onUpdate("restrict");
            $table->foreignId("section_id")->constrained()->onDelete("restrict")->onUpdate("restrict");
            $table->string("name",50);
            $table->string("last_name",50);
            $table->date("date_birth");
            $table->string("email",100)->nullable();
            $table->string("ci",30)->unique();
            $table->string("phone_number",30)->nullable();
            $table->string("sex",30)->nullable();
            $table->string("previous_school",200)->nullable();
            $table->string("photo",100)->default('guest.webp');
            $table->integer("status")->default(1);
            $table->string("search")->nullable();
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('students');
    }
}
