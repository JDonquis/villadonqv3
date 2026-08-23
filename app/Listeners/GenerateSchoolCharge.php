<?php

namespace App\Listeners;

use App\Models\SchoolCharge;
use App\Models\SchoolLapse;
use Illuminate\Support\Facades\Log;

class GenerateSchoolCharge
{
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

        Log::info('Status',[
            'student_id' => $student->id,
            'school_lapse_id' => $schoolLapseActive->id,
            'amount' => SchoolCharge::AMOUNT,
            'status' => 'pending',
            'student_status' => $student->status,
        ]);

        SchoolCharge::updateOrCreate(
            [
                'student_id' => $student->id,
                'school_lapse_id' => $schoolLapseActive->id,
            ],
            [
                'amount' => SchoolCharge::AMOUNT,
                'status' => 'pending',
                'student_status' => $student->status,
            ]
        );
    }
}
