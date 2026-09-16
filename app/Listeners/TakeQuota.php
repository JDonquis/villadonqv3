<?php

namespace App\Listeners;

use App\Models\Quota;
use App\Models\SchoolLapse;
use Illuminate\Support\Facades\DB;

class TakeQuota
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $student = $event->student;
        $schoolLapseActive = SchoolLapse::where('status', 1)->first();

        $quota = Quota::where('school_lapse_id', $schoolLapseActive->id)
            ->where('course_id', $student->course_id)
            ->first();

        $balanceExists = DB::table('balance_students')
            ->where('student_id', $student->id)
            ->where('school_lapse_id', $schoolLapseActive->id)
            ->exists();

        if ($balanceExists) {
            return;
        }

        $quota->decrement('remaining');
        $quota->increment('accepted');
        $quota->save();
    }
}
