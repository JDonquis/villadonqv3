<?php

namespace App\Services;

use App\Models\Course;
use App\Models\EvaluationPlan;
use App\Models\GradeSheet;
use App\Models\GradeSheetAdjustment;
use App\Models\SchoolLapse;
use App\Models\Student;
use Illuminate\Support\Collection;

class GradeSheetService
{
    public const MAX_ADJUSTMENT = 5;

    private StudentGradeService $gradeService;

    public function __construct()
    {
        $this->gradeService = new StudentGradeService;
    }

    /**
     * Crea (o recupera) la sábana de un año en un período escolar. Una sábana es
     * única por combinación período + año.
     */
    public function findOrCreate(int $schoolLapseId, int $courseId, int $userId): GradeSheet
    {
        return GradeSheet::firstOrCreate(
            [
                'school_lapse_id' => $schoolLapseId,
                'course_id' => $courseId,
            ],
            ['created_by' => $userId]
        );
    }

    public function findSheet(?int $schoolLapseId, ?int $courseId): ?GradeSheet
    {
        if (! $schoolLapseId || ! $courseId) {
            return null;
        }

        return GradeSheet::where('school_lapse_id', $schoolLapseId)
            ->where('course_id', $courseId)
            ->first();
    }

    /**
     * Construye la matriz de la sábana: estudiantes del año con la definitiva
     * publicada por materia en el momento seleccionado, más los puntos agregados.
     */
    public function getSheetData(GradeSheet $sheet, ?int $lapseId): array
    {
        $course = Course::with(['matters', 'section'])->find($sheet->course_id);
        $schoolLapse = SchoolLapse::with('lapses')->find($sheet->school_lapse_id);

        $lapses = $schoolLapse
            ? $schoolLapse->lapses->sortBy('number')->values()
            : collect();

        if (! $lapseId) {
            $lapseId = $lapses->last()?->id;
        }

        $students = Student::where('course_id', $sheet->course_id)
            ->where('status', '!=', 0)
            ->with('section:id,name')
            ->orderBy('section_id')
            ->orderBy('last_name')
            ->orderBy('name')
            ->get();

        $plans = EvaluationPlan::with(['items', 'matter'])
            ->where('school_lapse_id', $sheet->school_lapse_id)
            ->where('course_id', $sheet->course_id)
            ->where('status', 'approved')
            ->when($lapseId, fn ($q) => $q->where('lapse_id', $lapseId))
            ->get();

        $plansByMatterSection = $plans->keyBy(fn ($plan) => $plan->matter_id.'_'.$plan->section_id);

        $matters = $this->resolveMatters($course, $plans);

        $adjustments = GradeSheetAdjustment::where('grade_sheet_id', $sheet->id)->get()
            ->keyBy(fn ($a) => $a->student_id.'_'.$a->matter_id);

        $studentsData = $students->map(function ($student) use ($matters, $plansByMatterSection, $adjustments) {
            $grades = [];

            foreach ($matters as $matter) {
                $plan = $plansByMatterSection->get($matter['id'].'_'.$student->section_id);

                $definitive = $plan
                    ? $this->gradeService->publishedDefinitiveForStudent($plan, $student->id)
                    : null;

                $points = (int) ($adjustments->get($student->id.'_'.$matter['id'])?->points ?? 0);

                $final = $definitive === null
                    ? null
                    : round(min(StudentGradeService::MAX_SCORE, $definitive + $points), 2);

                $grades[$matter['id']] = [
                    'definitive' => $definitive !== null ? round($definitive, 2) : null,
                    'points' => $points,
                    'final' => $final,
                    'has_plan' => (bool) $plan,
                ];
            }

            return [
                'id' => $student->id,
                'name' => $student->name,
                'last_name' => $student->last_name,
                'ci' => $student->ci,
                'section_id' => $student->section_id,
                'section_name' => $student->section?->name,
                'grades' => $grades,
            ];
        })->values()->all();

        return [
            'sheet' => [
                'id' => $sheet->id,
                'school_lapse_id' => $sheet->school_lapse_id,
                'course_id' => $sheet->course_id,
            ],
            'course' => $course ? [
                'id' => $course->id,
                'name' => $course->name,
            ] : null,
            'school_lapse' => $schoolLapse ? [
                'id' => $schoolLapse->id,
                'label' => $this->periodLabel($schoolLapse),
            ] : null,
            'lapses' => $lapses->map(fn ($l) => [
                'id' => $l->id,
                'number' => $l->number,
                'label' => $this->momentLabel($l),
            ])->values()->all(),
            'selected_lapse_id' => $lapseId,
            'matters' => $matters,
            'students' => $studentsData,
            'max_adjustment' => self::MAX_ADJUSTMENT,
        ];
    }

    /**
     * Guarda los puntos agregados de la sábana. Solo acepta estudiantes del año
     * y materias del mismo, con puntos entre 0 y MAX_ADJUSTMENT.
     */
    public function saveAdjustments(GradeSheet $sheet, array $adjustments): int
    {
        $studentIds = Student::where('course_id', $sheet->course_id)
            ->where('status', '!=', 0)
            ->pluck('id');

        $matterIds = Course::find($sheet->course_id)?->matters()->pluck('matters.id') ?? collect();

        $saved = 0;

        foreach ($adjustments as $adjustment) {
            $studentId = (int) ($adjustment['student_id'] ?? 0);
            $matterId = (int) ($adjustment['matter_id'] ?? 0);
            $points = (int) ($adjustment['points'] ?? 0);

            if (! $studentIds->contains($studentId) || ! $matterIds->contains($matterId)) {
                continue;
            }

            $points = max(0, min(self::MAX_ADJUSTMENT, $points));

            if ($points === 0) {
                GradeSheetAdjustment::where('grade_sheet_id', $sheet->id)
                    ->where('student_id', $studentId)
                    ->where('matter_id', $matterId)
                    ->delete();

                continue;
            }

            GradeSheetAdjustment::updateOrCreate(
                [
                    'grade_sheet_id' => $sheet->id,
                    'student_id' => $studentId,
                    'matter_id' => $matterId,
                ],
                ['points' => $points]
            );

            $saved++;
        }

        return $saved;
    }

    public function deleteSheet(GradeSheet $sheet): void
    {
        $sheet->delete();
    }

    /**
     * Materias del año. Se prefiere la malla del curso; si no tiene, se usan las
     * materias que aparecen en los planes aprobados del período.
     */
    private function resolveMatters(?Course $course, Collection $plans): array
    {
        $matters = $course?->matters ?? collect();

        if ($matters->isEmpty()) {
            $matters = $plans->map(fn ($plan) => $plan->matter)->filter()->unique('id')->values();
        }

        return $matters
            ->sortBy('name')
            ->values()
            ->map(fn ($matter) => [
                'id' => $matter->id,
                'name' => $matter->name,
            ])
            ->all();
    }

    private function periodLabel(?SchoolLapse $lapse): ?string
    {
        if (! $lapse) {
            return null;
        }

        return $lapse->start && $lapse->end
            ? date('Y', strtotime($lapse->start)).' - '.date('Y', strtotime($lapse->end))
            : null;
    }

    private function momentLabel($lapse): ?string
    {
        if (! $lapse || ! $lapse->number) {
            return null;
        }

        $ordinals = [1 => '1er', 2 => '2do', 3 => '3er'];

        return ($ordinals[$lapse->number] ?? $lapse->number).' Momento';
    }
}
