<?php

namespace App\Listeners;

use App\Models\Quota;
use App\Models\SchoolLapse;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateTakeQuota
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
        $courseId = $event->courseId;
        $schoolLapseActive = SchoolLapse::where('status',1)->first();

        $quota = Quota::where('school_lapse_id', $schoolLapseActive->id)
        ->where('course_id',$courseId)
        ->first();
        $quota->decrement('accepted');
        $quota->increment('remaining');
        $quota->save();

        $newQuota = Quota::where('school_lapse_id', $schoolLapseActive->id)
        ->where('course_id',$student->course_id)
        ->first();
        $newQuota->decrement('remaining');
        $newQuota->increment('accepted');
        $newQuota->save();
    }
    
}
