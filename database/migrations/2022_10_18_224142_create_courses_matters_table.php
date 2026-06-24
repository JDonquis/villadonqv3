<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCoursesMattersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('courses_matters', function (Blueprint $table) {
            $table->id();
            $table->foreignId("course_id")->constrained()->onDelete("restrict")->onUpdate("restrict");
            $table->foreignId("matter_id")->constrained()->onDelete("restrict")->onUpdate("restrict");
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('courses_matters');
    }
}
