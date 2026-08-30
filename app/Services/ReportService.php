<?php

namespace App\Services;

use App\Models\EvaluationPlan;
use App\Models\Lapse;
use App\Models\MainConfig;
use App\Models\SchoolLapse;
use App\Models\Student;
use Carbon\Carbon;

class ReportService
{
    private StudentGradeService $gradeService;

    public function __construct()
    {
        $this->gradeService = new StudentGradeService;
    }

    public function getStudentReportData(int $studentId, ?int $lapseId = null): array
    {
        $student = Student::with(['course.matters', 'section', 'representative.user'])
            ->findOrFail($studentId);

        $period = $this->resolvePeriod($lapseId);
        $lapses = $period?->lapses->sortBy('number')->values() ?? collect();

        $selectedLapse = null;
        if ($lapseId) {
            $selectedLapse = $lapses->firstWhere('id', $lapseId);
        }

        $matters = $this->resolveMatters($student, $period);
        $plans = $this->getPlansForPeriod($student, $period?->id);
        $plansByMatterLapse = $plans->groupBy(fn ($plan) => $plan->matter_id.'_'.($plan->lapse_id ?? 'null'));

        $subjects = $matters->map(function ($matter) use ($plansByMatterLapse, $lapses, $selectedLapse, $student) {
            $lapseData = [];
            $hasAnyPlan = false;

            foreach ($lapses as $lapse) {
                $plan = $plansByMatterLapse->get($matter->id.'_'.$lapse->id)?->first();

                if ($plan) {
                    $hasAnyPlan = true;
                }

                $lapseData[$lapse->number] = [
                    'number' => $lapse->number,
                    'definitive' => $plan ? $this->gradeService->definitiveForStudent($plan, $student->id) : null,
                    'has_plan' => (bool) $plan,
                ];
            }

            if ($selectedLapse) {
                $current = $lapseData[$selectedLapse->number] ?? null;
                $annual = $current ? $current['definitive'] : null;
            } else {
                $definitives = collect($lapseData)->pluck('definitive')->filter(fn ($v) => $v !== null)->values();
                $annual = $definitives->isEmpty() ? null : round($definitives->avg(), 2);
            }

            return [
                'name' => $matter->name,
                'lapses' => $lapseData,
                'annual' => $annual,
                'status' => $this->statusFor($annual, $hasAnyPlan),
            ];
        })->values()->all();

        return [
            'config' => MainConfig::first(),
            'student' => [
                'name' => $student->name,
                'last_name' => $student->last_name,
                'document_type' => $student->document_type,
                'ci' => $student->ci,
                'age' => Carbon::parse($student->date_birth)->age,
                'course' => $student->course?->name,
                'section' => $student->section?->name,
                'rep_name' => $student->representative?->user?->name,
                'rep_last_name' => $student->representative?->user?->last_name,
                'rep_ci' => $student->representative?->user?->ci,
            ],
            'period' => $period ? [
                'label' => $this->periodLabel($period),
            ] : null,
            'lapses' => $lapses->map(fn ($l) => [
                'id' => $l->id,
                'number' => $l->number,
                'label' => $this->momentLabel($l),
            ])->values()->all(),
            'selected_lapse' => $selectedLapse ? [
                'number' => $selectedLapse->number,
                'label' => $this->momentLabel($selectedLapse),
            ] : null,
            'subjects' => $subjects,
            'current_date' => Carbon::now()->format('d/m/Y'),
        ];
    }

    private function resolvePeriod(?int $lapseId): ?SchoolLapse
    {
        if ($lapseId) {
            $lapse = Lapse::find($lapseId);

            if ($lapse) {
                return $lapse->schoolLapse()->with('lapses')->first();
            }
        }

        return SchoolLapse::where('status', 1)->with('lapses')->first()
            ?? SchoolLapse::with('lapses')->orderByDesc('start')->first();
    }

    private function resolveMatters(Student $student, ?SchoolLapse $period)
    {
        $matters = $student->course?->matters ?? collect();

        if ($matters->isNotEmpty()) {
            return $matters;
        }

        if ($period) {
            $mattersFromPlans = EvaluationPlan::where('course_id', $student->course_id)
                ->where('school_lapse_id', $period->id)
                ->with('matter')
                ->get()
                ->pluck('matter')
                ->filter()
                ->unique('id')
                ->values();

            if ($mattersFromPlans->isNotEmpty()) {
                return $mattersFromPlans;
            }
        }

        return $matters;
    }

    private function getPlansForPeriod(Student $student, ?int $periodId)
    {
        if (! $periodId) {
            return collect();
        }

        return EvaluationPlan::with(['matter', 'lapse', 'items.grades' => function ($q) use ($student) {
            $q->where('student_id', $student->id);
        }])
            ->where('course_id', $student->course_id)
            ->where('school_lapse_id', $periodId)
            ->get();
    }

    private function statusFor(?float $annual, bool $hasAnyPlan): array
    {
        if ($annual !== null) {
            return $annual >= StudentGradeService::PASSING_SCORE
                ? ['value' => 'aprobada', 'label' => 'Aprobada']
                : ['value' => 'reprobada', 'label' => 'Reprobada'];
        }

        return $hasAnyPlan
            ? ['value' => 'en_curso', 'label' => 'En curso']
            : ['value' => 'sin_nota', 'label' => 'Sin nota'];
    }

    private function periodLabel(SchoolLapse $lapse): string
    {
        return Carbon::parse($lapse->start)->year.' - '.Carbon::parse($lapse->end)->year;
    }

    private function momentLabel($lapse): string
    {
        $ordinals = [1 => '1er', 2 => '2do', 3 => '3er'];

        return ($ordinals[$lapse->number] ?? $lapse->number).' Momento';
    }
}
