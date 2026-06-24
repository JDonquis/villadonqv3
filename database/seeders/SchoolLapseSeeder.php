<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SchoolLapseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $currentYear = Carbon::now()->year;

        $currentYear = Carbon::now()->month >= 9 ? $currentYear : $currentYear - 1;

        $start = Carbon::create($currentYear, 9, 1, 0, 0, 0);

        $end = Carbon::create($currentYear + 1, 8, 31, 23, 59, 59);

        DB::table('school_lapses')->insert([
            'start' => $start,
            'end' => $end,
            'status' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
    }
}
