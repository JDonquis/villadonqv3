<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Lapse;
use App\Models\Quota;
use App\Models\SchoolLapse;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateNextSchoolLapse extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lapse:start-next';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea el próximo periodo escolar, lo activa y renueva los cupos de los cursos.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando creación del próximo periodo escolar...');

        $currentLapse = SchoolLapse::where('status', 1)->first();
        $latestLapse = SchoolLapse::orderBy('end', 'desc')->first();

        $startDate = null;
        $endDate = null;

        if ($latestLapse) {
            $lastEnd = Carbon::parse($latestLapse->end);
            $startDate = $lastEnd->copy()->addDay();
            if ($lastEnd->month == 8) {
                $startDate = Carbon::create($lastEnd->year, 9, 1);
            }
            $endDate = $startDate->copy()->addYear()->subDay();
            $endDate = Carbon::create($startDate->year + ($startDate->month >= 9 ? 1 : 0), 8, 31);
        } else {
            $now = Carbon::now();
            $year = $now->month >= 9 ? $now->year : $now->year - 1;
            $startDate = Carbon::create($year, 9, 1);
            $endDate = Carbon::create($year + 1, 8, 31);
        }

        $this->info("Nuevo periodo: {$startDate->toDateString()} al {$endDate->toDateString()}");

        DB::transaction(function () use ($startDate, $endDate, $currentLapse) {
            // Desactivar todos los periodos actuales
            SchoolLapse::query()->update(['status' => 0]);

            // Crear y activar el nuevo periodo
            $newLapse = SchoolLapse::create([
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
                'status' => 1,
            ]);

            $this->info("Periodo escolar #{$newLapse->id} creado y activado.");

            // Crear los 3 momentos escolares (lapsos)
            $seedLapses = new SeedLapses;
            foreach ($seedLapses->buildLapseRanges($newLapse) as $number => $range) {
                Lapse::create([
                    'start' => $range['start'],
                    'end' => $range['end'],
                    'number' => $number,
                    'school_lapse_id' => $newLapse->id,
                ]);
            }

            $this->info('Momentos escolares creados.');

            // Renovar cupos (Quotas)
            $this->info('Renovando cupos para el nuevo periodo...');

            $courses = Course::all();
            foreach ($courses as $course) {
                // Intentamos buscar el cupo del periodo anterior para copiar la capacidad (assigned)
                $previousQuota = null;
                if ($currentLapse) {
                    $previousQuota = Quota::where('course_id', $course->id)
                        ->where('school_lapse_id', $currentLapse->id)
                        ->first();
                }

                $assigned = $previousQuota ? $previousQuota->assigned : 0;

                Quota::create([
                    'course_id' => $course->id,
                    'school_lapse_id' => $newLapse->id,
                    'assigned' => $assigned,
                    'accepted' => 0,
                    'remaining' => $assigned,
                ]);
            }

            $this->info('Cupos renovados para '.$courses->count().' cursos.');
        });

        $this->info('Proceso finalizado con éxito.');
    }
}
