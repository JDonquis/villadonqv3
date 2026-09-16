<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Quota;
use App\Models\SchoolLapse;
use App\Models\Student;
use Illuminate\Console\Command;

class RecountQuotas extends Command
{
    protected $signature = 'quota:recount';

    protected $description = 'Recuenta los cupos (accepted/remaining) por curso basándose en los estudiantes activos actuales.';

    public function handle()
    {
        $schoolLapse = SchoolLapse::where('status', 1)->first();

        if (! $schoolLapse) {
            $this->warn('No hay un periodo escolar activo.');

            return 0;
        }

        $this->info("Periodo escolar: #{$schoolLapse->id} ({$schoolLapse->start} - {$schoolLapse->end})");
        $this->line('');

        $quotas = Quota::where('school_lapse_id', $schoolLapse->id)->get();
        $updated = 0;
        $totalAccepted = 0;
        $totalRemaining = 0;

        foreach ($quotas as $quota) {
            $actualCount = Student::where('course_id', $quota->course_id)
                ->where('status', 1)
                ->count();

            $course = Course::where('id', $quota->course_id)->value('name');
            $oldAccepted = $quota->accepted;

            $quota->update([
                'accepted' => $actualCount,
                'remaining' => max(0, (int) $quota->assigned - $actualCount),
            ]);

            $totalAccepted += $actualCount;
            $totalRemaining += max(0, (int) $quota->assigned - $actualCount);
            $updated++;

            if ($oldAccepted !== $actualCount) {
                $this->warn("  {$course}: accepted {$oldAccepted} → {$actualCount}, remaining = ".max(0, (int) $quota->assigned - $actualCount));
            } else {
                $this->info("  {$course}: accepted = {$actualCount}, remaining = ".max(0, (int) $quota->assigned - $actualCount));
            }
        }

        $this->line('');
        $this->info("Actualizadas {$updated} cuotas. Total accepted: {$totalAccepted}, Total remaining: {$totalRemaining}.");

        return 0;
    }
}
