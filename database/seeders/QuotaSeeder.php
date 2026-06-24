<?php

namespace Database\Seeders;

use App\Models\SchoolLapse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuotaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $schoolLapseId = SchoolLapse::where('status', 1)->first()->id;

        $fields = [

            ['assigned' => 500, 'accepted' => 0, 'remaining' => 500, 'course_id' => 1, 'school_lapse_id' => $schoolLapseId],
            ['assigned' => 500, 'accepted' => 0, 'remaining' => 500, 'course_id' => 2, 'school_lapse_id' => $schoolLapseId],
            ['assigned' => 500, 'accepted' => 0, 'remaining' => 500, 'course_id' => 3, 'school_lapse_id' => $schoolLapseId],
            ['assigned' => 500, 'accepted' => 0, 'remaining' => 500, 'course_id' => 4, 'school_lapse_id' => $schoolLapseId],
            ['assigned' => 500, 'accepted' => 0, 'remaining' => 500, 'course_id' => 5, 'school_lapse_id' => $schoolLapseId],
            ['assigned' => 500, 'accepted' => 0, 'remaining' => 500, 'course_id' => 6, 'school_lapse_id' => $schoolLapseId],
            ['assigned' => 500, 'accepted' => 0, 'remaining' => 500, 'course_id' => 7, 'school_lapse_id' => $schoolLapseId],
            ['assigned' => 500, 'accepted' => 0, 'remaining' => 500, 'course_id' => 8, 'school_lapse_id' => $schoolLapseId],
            ['assigned' => 500, 'accepted' => 0, 'remaining' => 500, 'course_id' => 9, 'school_lapse_id' => $schoolLapseId],
            ['assigned' => 500, 'accepted' => 0, 'remaining' => 500, 'course_id' => 10, 'school_lapse_id' => $schoolLapseId],
            ['assigned' => 500, 'accepted' => 0, 'remaining' => 500, 'course_id' => 11, 'school_lapse_id' => $schoolLapseId],
            ['assigned' => 500, 'accepted' => 0, 'remaining' => 500, 'course_id' => 12, 'school_lapse_id' => $schoolLapseId],
            ['assigned' => 500, 'accepted' => 0, 'remaining' => 500, 'course_id' => 13, 'school_lapse_id' => $schoolLapseId],
            ['assigned' => 500, 'accepted' => 0, 'remaining' => 500, 'course_id' => 14, 'school_lapse_id' => $schoolLapseId],

        ];

        DB::table('quotas')->insert($fields);
    }
}
