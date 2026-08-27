<?php

namespace App\Listeners;

use App\Models\Inscription;
use App\Models\SchoolLapse;

class GenerateInscription
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

        if (! $schoolLapseActive) {
            return;
        }

        $exists = Inscription::where('student_id', $student->id)
            ->where('school_lapse_id', $schoolLapseActive->id)
            ->exists();

        if ($exists) {
            return;
        }

        Inscription::create([
            'school_lapse_id' => $schoolLapseActive->id,
            'course_id' => $student->course_id,
            'section_id' => $student->section_id,
            'student_id' => $student->id,
        ]);
    }
}
