<?php

namespace App\Console\Commands;

use App\Models\SchoolCharge;
use Illuminate\Console\Command;

class ReconcileSchoolCharges extends Command
{
    protected $signature = 'school-charges:reconcile-status';

    protected $description = 'Actualiza student_status de los cobros según el estado actual del estudiante.';

    public function handle()
    {
        $charges = SchoolCharge::with('student')->get();

        if ($charges->isEmpty()) {
            $this->warn('No hay cobros para reconciliar.');

            return 0;
        }

        $updated = 0;
        foreach ($charges as $charge) {
            if (! $charge->student) {
                continue;
            }

            $newStatus = $charge->student->status;
            if ((string) $charge->student_status !== (string) $newStatus) {
                $charge->update(['student_status' => $newStatus]);
                $updated++;
            }
        }

        $this->info("Reconciliación completada. Se actualizaron {$updated} cobros de ".$charges->count().'.');

        return 0;
    }
}
