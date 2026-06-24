<?php

namespace App\Listeners;

use App\Models\Inscription;
use App\Models\SchoolLapse;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

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

        Inscription::create([
            'school_lapse_id' => $schoolLapseActive->id,
            'student_id' => $student->id,
        ]);
    }
}
