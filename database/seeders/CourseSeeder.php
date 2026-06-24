<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use DB;
class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $fields = 
        [

            ['name' => '5to Año' ],
            ['name' => '4to Año' ],
            ['name' => '3er Año' ],
            ['name' => '2do Año' ],
            ['name' => '1er Año' ],

            ['name' => '6to Grado' ],
            ['name' => '5to Grado' ],
            ['name' => '4to Grado' ],
            ['name' => '3er Grado' ],   
            ['name' => '2do Grado' ],
            ['name' => '1er Grado' ],

            ['name' => '3er Nivel' ],
            ['name' => '2do Nivel' ],
            ['name' => '1er Nivel' ],


         ];   

         DB::table('courses')->insert($fields);
    }
}
