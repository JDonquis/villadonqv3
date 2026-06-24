<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use DB;


class CourseSectionSeeder extends Seeder
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

            ['course_id' => 1, 'section_id' => 1 ],
            ['course_id' => 2, 'section_id' => 1 ],
            ['course_id' => 3, 'section_id' => 1 ],
            ['course_id' => 4, 'section_id' => 1 ],
            ['course_id' => 5, 'section_id' => 1 ],

            ['course_id' => 6, 'section_id' => 1 ],
            ['course_id' => 7, 'section_id' => 1 ],
            ['course_id' => 8, 'section_id' => 1 ],
            ['course_id' => 9, 'section_id' => 1 ],
            ['course_id' => 10, 'section_id' => 1 ],
            ['course_id' => 11, 'section_id' => 1 ],
            
            ['course_id' => 12, 'section_id' => 1 ],
            ['course_id' => 13, 'section_id' => 1 ],
            ['course_id' => 14, 'section_id' => 1 ],
         ];   

         DB::table('course_sections')->insert($fields);
    }
}
