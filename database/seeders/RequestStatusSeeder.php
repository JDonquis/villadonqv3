<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use DB;
class RequestStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
         $status = [

            ['name' => 'Accepted'],
            ['name' => 'Rejected'],
            ['name' => 'No_check'],
         

         ];   

         DB::table('request_status')->insert($status);
    }
}
