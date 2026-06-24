<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use DB;

class SectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
         $fields = [

            ['name' => 'A' ],
            ['name' => 'B' ],
            ['name' => 'C' ],
            ['name' => 'D' ],
            ['name' => 'E' ],
            ['name' => 'F' ],
     

         ];   

         DB::table('sections')->insert($fields);
    }
}
