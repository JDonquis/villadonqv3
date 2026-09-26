<?php

namespace App\Console\Commands;

use App\Models\Inscription;
use App\Services\StudentChargeService;
use Illuminate\Console\Command;

class BackfillStudentCharges extends Command
{
    protected $signature = 'student-charges:backfill';

    protected $description = 'Genera los cargos de AME y Plan de inversión para las inscripciones existentes según la configuración actual.';

    public function handle()
    {
        $inscriptions = Inscription::with(['student', 'schoolLapse'])
            ->whereHas('student', function ($query) {
                $query->where('status', '!=', 0);
            })
            ->get();

        if ($inscriptions->isEmpty()) {
            $this->warn('No hay inscripciones registradas para generar cargos.');

            return 0;
        }

        $service = new StudentChargeService;
        $created = 0;

        foreach ($inscriptions as $inscription) {
            $student = $inscription->student;
            $lapse = $inscription->schoolLapse;

            if (! $student || ! $lapse) {
                continue;
            }

            $before = $student->charges()
                ->where('school_lapse_id', $lapse->id)
                ->count();

            $service->generateForStudent($student, $lapse);

            $after = $student->charges()
                ->where('school_lapse_id', $lapse->id)
                ->count();

            $created += max(0, $after - $before);
        }

        $this->info("Backfill completado. Se generaron {$created} cargos a partir de ".$inscriptions->count().' inscripciones.');

        return 0;
    }
}
