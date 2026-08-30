<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\SchoolLapse;

class MyScheduleService
{
    /**
     * Datos para la vista de "Mi Horario" de un profesor.
     * Solo el periodo (active lapse by default) y todas las clases que el
     * profesor imparte en ese periodo, agrupadas por dia, cada una etiquetada
     * con su curso y sección (sin campo de profesor, es el propio usuario).
     */
    public function getIndexData(int $teacherId, ?int $schoolLapseId = null): array
    {
        $periods = SchoolLapse::orderBy('start', 'desc')->get()
            ->map(fn ($lapse) => [
                'id' => $lapse->id,
                'name' => $this->formatPeriod($lapse),
                'start' => $lapse->start,
                'end' => $lapse->end,
            ])
            ->values();

        $defaultLapse = SchoolLapse::where('status', 1)->latest('id')->first()
            ?? SchoolLapse::orderBy('start', 'desc')->first();

        $lapseId = $schoolLapseId
            ?? ($defaultLapse ? $defaultLapse->id : ($periods->first()['id'] ?? null));

        $schedules = Schedule::where('school_lapse_id', $lapseId)
            ->whereHas('classes', fn ($q) => $q->where('teacher_id', $teacherId))
            ->with('course', 'section')
            ->with(['classes' => fn ($q) => $q->where('teacher_id', $teacherId)->with('matter')])
            ->get();

        $days = [];

        foreach ($schedules as $schedule) {
            if (! $schedule->recess_start) {
                continue;
            }

            foreach ($schedule->classes as $class) {
                $days[$class->day][] = [
                    'id' => $class->id,
                    'start_time' => $class->start_time,
                    'end_time' => $class->end_time,
                    'matter_id' => (int) $class->matter_id,
                    'matter_name' => $class->matter->name ?? null,
                    'course_name' => $schedule->course?->name,
                    'section_name' => $schedule->section?->name,
                ];
            }
        }

        ksort($days);

        foreach ($days as $day => $rows) {
            usort($rows, fn ($a, $b) => strcmp($a['start_time'], $b['start_time']));
            $days[$day] = $rows;
        }

        return [
            'periods' => $periods,
            'lapse_id' => $lapseId,
            'days' => $days,
        ];
    }

    private function formatPeriod(SchoolLapse $lapse): string
    {
        $startYear = date('Y', strtotime($lapse->start));
        $endYear = date('Y', strtotime($lapse->end));

        return "$startYear - $endYear";
    }
}
