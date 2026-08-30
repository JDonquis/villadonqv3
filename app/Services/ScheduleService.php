<?php

namespace App\Services;

use App\Enums\UserTypeEnum;
use App\Http\Resources\CourseSectionCollection;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Matter;
use App\Models\Schedule;
use App\Models\SchoolLapse;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScheduleService
{
    public function getIndexData(): array
    {
        $courses = Course::all();
        $sections = Section::all();
        $courseSections = new CourseSectionCollection(CourseSection::with('section', 'course')->get());

        $periods = SchoolLapse::orderBy('start', 'desc')->get()->map(fn ($lapse) => [
            'id' => $lapse->id,
            'name' => $this->formatPeriod($lapse),
            'start' => $lapse->start,
            'end' => $lapse->end,
        ])->values();

        $matters = Matter::orderBy('name')->get()->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
        ])->values();

        $teachers = User::where('type_user_id', UserTypeEnum::Teacher->value)
            ->with('matters')
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'last_name' => $t->last_name,
                'full_name' => trim($t->name.' '.$t->last_name),
                'matter_ids' => $t->matters->pluck('id')->map(fn ($id) => (int) $id)->values(),
            ])
            ->values();

        return [
            'periods' => $periods,
            'courses' => $courses,
            'sections' => $sections,
            'course_sections' => $courseSections,
            'matters' => $matters,
            'teachers' => $teachers,
        ];
    }

    public function get(int $schoolLapseId, int $courseId, int $sectionId): ?array
    {
        $schedule = Schedule::with('classes.matter', 'classes.teacher')
            ->where('school_lapse_id', $schoolLapseId)
            ->where('course_id', $courseId)
            ->where('section_id', $sectionId)
            ->first();

        if (! $schedule) {
            return null;
        }

        return $this->toArray($schedule);
    }

    public function save(array $data): Schedule
    {
        $schoolLapseId = (int) $data['school_lapse_id'];
        $courseId = (int) $data['course_id'];
        $sectionId = (int) $data['section_id'];

        $schedule = Schedule::firstOrNew([
            'school_lapse_id' => $schoolLapseId,
            'course_id' => $courseId,
            'section_id' => $sectionId,
        ]);

        $existingScheduleId = $schedule->exists ? $schedule->id : null;

        $this->validateTeacherConflicts($data, $schoolLapseId, $existingScheduleId);

        return DB::transaction(function () use ($schedule, $schoolLapseId, $courseId, $sectionId, $data) {
            $schedule->school_lapse_id = $schoolLapseId;
            $schedule->course_id = $courseId;
            $schedule->section_id = $sectionId;
            $schedule->recess_start = $data['recess_start'] ?? null;
            $schedule->recess_duration_minutes = $data['recess_duration_minutes'] ?? null;
            $schedule->save();

            $schedule->classes()->delete();

            $days = $data['days'] ?? [];
            $order = 0;

            foreach (range(1, 5) as $day) {
                $rows = $days[$day] ?? [];
                foreach ($rows as $row) {
                    $schedule->classes()->create([
                        'day' => $day,
                        'start_time' => $row['start_time'] ?? null,
                        'end_time' => $row['end_time'] ?? null,
                        'matter_id' => $row['matter_id'] ?? null,
                        'teacher_id' => $row['teacher_id'] ?? null,
                        'order' => $order++,
                    ]);
                }
            }

            return $schedule;
        });
    }

    /**
     * Valida que ningun profesor quede asignado a dos clases que se superpongan
     * en el mismo dia, comparando contra las clases de OTRAS secciones/cursos
     * del mismo periodo. Excluye el schedule actual para permitir re-guardar.
     *
     * @throws \Exception
     */
    private function validateTeacherConflicts(array $data, int $schoolLapseId, ?int $excludeScheduleId): void
    {
        $days = $data['days'] ?? [];

        $occupancy = collect($this->teacherOccupancy($schoolLapseId, $excludeScheduleId));

        foreach (range(1, 5) as $day) {
            $rows = $days[$day] ?? [];

            foreach ($rows as $row) {
                $teacherId = isset($row['teacher_id']) ? (int) $row['teacher_id'] : null;
                $start = $row['start_time'] ?? null;
                $end = $row['end_time'] ?? null;

                if (! $teacherId || ! $start || ! $end || $start >= $end) {
                    continue;
                }

                $conflict = $occupancy->first(function ($block) use ($teacherId, $day, $start, $end) {
                    if ((int) $block['teacher_id'] !== $teacherId || (int) $block['day'] !== $day) {
                        return false;
                    }

                    return $start < $block['end_time'] && $block['start_time'] < $end;
                });

                if ($conflict) {
                    $teacher = User::find($teacherId);
                    $teacherName = $teacher ? trim($teacher->name.' '.$teacher->last_name) : "Profesor #{$teacherId}";

                    throw new \Exception(sprintf(
                        'El profesor %s ya está ocupado el %s de %s a %s (%s · Sección %s). Revisa el horario.',
                        $teacherName,
                        $this->dayName($day),
                        $this->formatTime($start),
                        $this->formatTime($end),
                        $conflict['course_name'] ?? 'otra clase',
                        $conflict['section_name'] ?? '—'
                    ));
                }
            }
        }
    }

    /**
     * Bloques ocupados por cada profesor en el periodo, de otras secciones/cursos.
     */
    public function teacherOccupancy(int $schoolLapseId, ?int $excludeScheduleId = null): array
    {
        $schedules = Schedule::where('school_lapse_id', $schoolLapseId)
            ->when($excludeScheduleId, fn ($q) => $q->where('id', '!=', $excludeScheduleId))
            ->with('course', 'section')
            ->with(['classes' => fn ($q) => $q->whereNotNull('teacher_id')->with('teacher')])
            ->get();

        $blocks = [];

        foreach ($schedules as $schedule) {
            foreach ($schedule->classes as $class) {
                $blocks[] = [
                    'teacher_id' => (int) $class->teacher_id,
                    'teacher_name' => $class->teacher ? trim($class->teacher->name.' '.$class->teacher->last_name) : null,
                    'day' => (int) $class->day,
                    'start_time' => $class->start_time,
                    'end_time' => $class->end_time,
                    'course_name' => $schedule->course?->name,
                    'section_name' => $schedule->section?->name,
                ];
            }
        }

        return $blocks;
    }

    private function dayName(int $day): string
    {
        $names = [
            1 => 'lunes',
            2 => 'martes',
            3 => 'miércoles',
            4 => 'jueves',
            5 => 'viernes',
        ];

        return $names[$day] ?? "día {$day}";
    }

    private function formatTime(string $hhmm): string
    {
        $parts = explode(':', $hhmm);
        $h = (int) $parts[0];
        $m = $parts[1] ?? '00';

        return sprintf('%02d:%02d', $h, $m);
    }

    private function toArray(Schedule $schedule): array
    {
        $days = [];

        foreach ($schedule->classes as $class) {
            $days[$class->day][] = [
                'id' => $class->id,
                'start_time' => $class->start_time,
                'end_time' => $class->end_time,
                'matter_id' => (int) $class->matter_id,
                'matter_name' => $class->matter->name ?? null,
                'teacher_id' => (int) $class->teacher_id,
                'teacher_name' => $class->teacher ? trim($class->teacher->name.' '.$class->teacher->last_name) : null,
            ];
        }

        return [
            'id' => $schedule->id,
            'recess_start' => $schedule->recess_start,
            'recess_duration_minutes' => $schedule->recess_duration_minutes,
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
