<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UserModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('user_modules')->insert
        (
            [
                ['user_id' => 1, 'module_id' => 1],
                ['user_id' => 1, 'module_id' => 2],
            ]    

        );
    }
}
