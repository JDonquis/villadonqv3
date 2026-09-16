<?php

namespace App\Listeners;

use App\Models\Inscription;
use App\Models\Quota;
use App\Models\SchoolLapse;

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
        $schoolLapseActive = SchoolLapse::where('status', 1)->first();

        $oldInscription = Inscription::where('student_id', $student->id)
            ->where('course_id', $courseId)
            ->where('school_lapse_id', $schoolLapseActive->id)
            ->first();

        if (! $oldInscription) {
            return;
        }

        $quota = Quota::where('school_lapse_id', $schoolLapseActive->id)
            ->where('course_id', $courseId)
            ->first();
        if ($quota) {
            $quota->decrement('accepted');
            $quota->increment('remaining');
            $quota->save();
        }

        $oldInscription->update(['course_id' => $student->course_id]);

        $newInscriptionExists = Inscription::where('student_id', $student->id)
            ->where('course_id', $student->course_id)
            ->where('school_lapse_id', $schoolLapseActive->id)
            ->exists();

        if ($newInscriptionExists) {
            return;
        }

        $newQuota = Quota::where('school_lapse_id', $schoolLapseActive->id)
            ->where('course_id', $student->course_id)
            ->first();
        if ($newQuota) {
            $newQuota->decrement('remaining');
            $newQuota->increment('accepted');
            $newQuota->save();
        }
    }
}
