<?php

namespace App\Services;

use App\Models\EvaluationPlan;
use App\Models\Student;
use App\Models\StudentGrade;
use Carbon\Carbon;

class StudentGradeService
{
    public const PASSING_SCORE = 10;

    public const MAX_SCORE = 20;

    public function getMatrixData(int $planId): array
    {
        $plan = EvaluationPlan::with(['items', 'course', 'section', 'matter', 'lapse', 'schoolLapse'])
            ->findOrFail($planId);

        $students = Student::where('course_id', $plan->course_id)
            ->where('section_id', $plan->section_id)
            ->where('status', '!=', 0)
            ->orderBy('last_name')
            ->orderBy('name')
            ->get();

        $itemIds = $plan->items->pluck('id');
        $grades = StudentGrade::whereIn('plan_item_id', $itemIds)->get();

        $gradesByItemStudent = [];
        foreach ($grades as $grade) {
            $gradesByItemStudent[$grade->plan_item_id][$grade->student_id] = (float) $grade->score;
        }

        $units = $plan->items
            ->groupBy(fn ($item) => $item->unit_name ?? 'Unidad 1')
            ->map(function ($group, $unitName) {
                $unitNumber = $group->first()->unit_number ?? 1;

                return [
                    'id' => 'unit_'.($group->first()->unit_number ?? 1),
                    'unit_number' => (int) $unitNumber,
                    'name' => $unitName,
                    'topics' => $group->map(fn ($item) => [
                        'id' => 'topic_'.$item->id,
                        'name' => $item->name,
                        'assessment_type' => $item->assessment_type,
                        'percentage' => (float) $item->percentage,
                        'points' => $item->points !== null ? (float) $item->points : null,
                        'scheduled_date' => $item->scheduled_date ?? $item->date,
                        'description' => $item->description,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();

        $items = $plan->items->map(function ($item) use ($units) {
            $unit = collect($units)->first(function ($u) use ($item) {
                return collect($u['topics'])->contains(fn ($topic) => $topic['name'] === $item->name && $topic['percentage'] == $item->percentage);
            });

            return [
                'id' => $item->id,
                'name' => $item->name,
                'percentage' => (float) $item->percentage,
                'date' => $item->date,
                'unit_number' => $unit['unit_number'] ?? ($item->unit_number ?? 1),
                'unit_name' => $unit['name'] ?? ($item->unit_name ?? 'Unidad 1'),
                'assessment_type' => $item->assessment_type ?? null,
                'scheduled_date' => $item->scheduled_date ?? $item->date,
            ];
        })->values()->all();

        $studentsData = $students->map(function ($student) use ($gradesByItemStudent, $items) {
            $scores = [];
            foreach ($items as $item) {
                $scores[$item['id']] = $gradesByItemStudent[$item['id']][$student->id] ?? null;
            }

            return [
                'id' => $student->id,
                'name' => $student->name,
                'last_name' => $student->last_name,
                'ci' => $student->ci,
                'scores' => $scores,
                'definitive' => $this->computeDefinitive($items, $scores),
            ];
        })->values()->all();

        return [
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'matter_name' => $plan->matter?->name,
                'school_lapse_label' => $this->lapseLabel($plan->schoolLapse),
                'lapse_label' => $this->momentLabel($plan->lapse),
                'course_name' => $plan->course?->name,
                'section_name' => $plan->section?->name,
            ],
            'items' => $items,
            'units' => $units,
            'students' => $studentsData,
        ];
    }

    public function saveGrades(int $planId, array $grades): int
    {
        $plan = EvaluationPlan::with('items')->findOrFail($planId);

        $itemIds = $plan->items->pluck('id');
        $studentIds = Student::where('course_id', $plan->course_id)
            ->where('section_id', $plan->section_id)
            ->where('status', '!=', 0)
            ->pluck('id');

        $saved = 0;
        foreach ($grades as $grade) {
            $itemId = (int) ($grade['plan_item_id'] ?? 0);
            $studentId = (int) ($grade['student_id'] ?? 0);

            if (! $itemIds->contains($itemId) || ! $studentIds->contains($studentId)) {
                continue;
            }

            $raw = $grade['score'] ?? null;
            $score = ($raw === null || $raw === '') ? null : (float) $raw;

            if ($score !== null) {
                $score = max(0, min(self::MAX_SCORE, $score));
            }

            StudentGrade::updateOrCreate(
                ['plan_item_id' => $itemId, 'student_id' => $studentId],
                ['score' => $score]
            );

            $saved++;
        }

        return $saved;
    }

    public function computeDefinitive(array $items, array $scores): ?float
    {
        $total = 0.0;

        foreach ($items as $item) {
            $score = $scores[$item['id']] ?? null;

            if ($score === null) {
                return null;
            }

            $total += (float) $score * ((float) $item['percentage'] / 100);
        }

        return round($total, 2);
    }

    public function definitiveForStudent(EvaluationPlan $plan, int $studentId): ?float
    {
        $scores = [];
        $items = [];

        foreach ($plan->items as $item) {
            $items[] = ['id' => $item->id, 'percentage' => (float) $item->percentage];
            $scores[$item->id] = $item->grades->firstWhere('student_id', $studentId)?->score ?? null;
        }

        return $this->computeDefinitive($items, $scores);
    }

    private function lapseLabel(?object $lapse): ?string
    {
        if (! $lapse) {
            return null;
        }

        return Carbon::parse($lapse->start)->year.' - '.Carbon::parse($lapse->end)->year;
    }

    private function momentLabel(?object $lapse): ?string
    {
        if (! $lapse || ! $lapse->number) {
            return null;
        }

        $ordinals = [1 => '1er', 2 => '2do', 3 => '3er'];

        return ($ordinals[$lapse->number] ?? $lapse->number).' Momento';
    }
}
