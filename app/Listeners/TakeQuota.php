<?php

namespace App\Listeners;

use App\Models\Quota;
use App\Models\SchoolLapse;
use App\Models\CourseSection;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

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
        $schoolLapseActive = SchoolLapse::where('status',1)->first();

        $quota = Quota::where('school_lapse_id', $schoolLapseActive->id)
        ->where('course_id',$student->course_id)
        ->first();
        $quota->decrement('remaining');
        $quota->increment('accepted');
        $quota->save();
    }
}
