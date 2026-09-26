<?php

namespace App\Listeners;

use App\Services\StudentChargeService;

class GenerateStudentCharges
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

        if (! $student) {
            return;
        }

        (new StudentChargeService)->generateForStudent($student);
    }
}
