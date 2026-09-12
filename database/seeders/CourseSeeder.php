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

            ['name' => '5to Año', 'plan_de_estudio_code' => '31022' ],
            ['name' => '4to Año', 'plan_de_estudio_code' => '31022' ],
            ['name' => '3er Año', 'plan_de_estudio_code' => '31022' ],
            ['name' => '2do Año', 'plan_de_estudio_code' => '31022' ],
            ['name' => '1er Año', 'plan_de_estudio_code' => '31022' ],

            ['name' => '6to Grado', 'plan_de_estudio_code' => '31011' ],
            ['name' => '5to Grado', 'plan_de_estudio_code' => '31011' ],
            ['name' => '4to Grado', 'plan_de_estudio_code' => '31011' ],
            ['name' => '3er Grado', 'plan_de_estudio_code' => '31011' ],   
            ['name' => '2do Grado', 'plan_de_estudio_code' => '31011' ],
            ['name' => '1er Grado', 'plan_de_estudio_code' => '31011' ],

            ['name' => '3er Nivel', 'plan_de_estudio_code' => null ],
            ['name' => '2do Nivel', 'plan_de_estudio_code' => null ],
            ['name' => '1er Nivel', 'plan_de_estudio_code' => null ],


         ];   

         DB::table('courses')->insert($fields);
    }
}
