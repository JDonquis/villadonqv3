<?php

namespace App\Console\Commands;

use App\Models\Inscription;
use Illuminate\Console\Command;

class BackfillInscriptionCourses extends Command
{
    protected $signature = 'inscriptions:backfill-courses';

    protected $description = 'Rellena course_id y section_id en inscripciones antiguas usando el curso/sección actual del estudiante.';

    public function handle()
    {
        $inscriptions = Inscription::with('student')->get();

        if ($inscriptions->isEmpty()) {
            $this->warn('No hay inscripciones registradas.');

            return 0;
        }

        $updated = 0;
        foreach ($inscriptions as $inscription) {
            if (! $inscription->student) {
                continue;
            }

            if (! $inscription->course_id || ! $inscription->section_id) {
                $inscription->update([
                    'course_id' => $inscription->student->course_id,
                    'section_id' => $inscription->student->section_id,
                ]);
                $updated++;
            }
        }

        $this->info("Backfill completado. Se actualizaron {$updated} inscripciones.");

        return 0;
    }
}
