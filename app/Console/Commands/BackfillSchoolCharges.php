<?php

namespace App\Console\Commands;

use App\Models\Inscription;
use App\Models\SchoolCharge;
use Illuminate\Console\Command;

class BackfillSchoolCharges extends Command
{
    protected $signature = 'school-charges:backfill';

    protected $description = 'Genera los cobros de $1 por estudiante/periodo a partir de las inscripciones existentes.';

    public function handle()
    {
        $inscriptions = Inscription::select('student_id', 'school_lapse_id')->whereHas('student', function ($query) {
            $query->where('status', 1);
        })->get();

        if ($inscriptions->isEmpty()) {
            $this->warn('No hay inscripciones registradas para generar cobros.');

            return 0;
        }

        $created = 0;
        foreach ($inscriptions as $inscription) {
            $charge = SchoolCharge::firstOrCreate(
                [
                    'student_id' => $inscription->student_id,
                    'school_lapse_id' => $inscription->school_lapse_id,
                ],
                [
                    'amount' => SchoolCharge::AMOUNT,
                ]
            );

            if ($charge->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->info("Backfill completado. Se generaron {$created} cobros a partir de ".$inscriptions->count().' inscripciones.');

        return 0;
    }
}
