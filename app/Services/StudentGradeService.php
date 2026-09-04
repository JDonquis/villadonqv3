<?php

namespace App\Services;

use App\Models\EvaluationPlan;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\StudentGradePublication;
use App\Models\StudentGradePublicationItem;
use App\Models\StudentGradePublicationRasgo;
use App\Models\StudentPlanRasgo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StudentGradeService
{
    public const PASSING_SCORE = 10;

    public const MAX_SCORE = 20;

    public function getMatrixData(int $planId): array
    {
        $plan = EvaluationPlan::with(['items', 'course', 'section', 'matter', 'lapse', 'schoolLapse', 'rasgos'])
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

        $rasgosByStudent = $plan->rasgos->pluck('rasgos_score', 'student_id');
        $planRasgosPoints = (int) $plan->rasgos_points;

        $studentsData = $students->map(function ($student) use ($gradesByItemStudent, $items, $rasgosByStudent, $planRasgosPoints) {
            $scores = [];
            foreach ($items as $item) {
                $scores[$item['id']] = $gradesByItemStudent[$item['id']][$student->id] ?? null;
            }

            $rasgos = $rasgosByStudent[$student->id] ?? null;

            return [
                'id' => $student->id,
                'name' => $student->name,
                'last_name' => $student->last_name,
                'ci' => $student->ci,
                'scores' => $scores,
                'rasgos' => $rasgos !== null ? (int) $rasgos : null,
                'definitive' => $this->computeDefinitive($items, $scores, $planRasgosPoints > 0 ? $rasgos : 0),
            ];
        })->values()->all();

        $latestPublication = StudentGradePublication::where('evaluation_plan_id', $plan->id)
            ->latest('version')
            ->with(['items', 'rasgos'])
            ->first();
        $draftGrades = $grades->mapWithKeys(fn ($grade) => [
            $grade->plan_item_id.'_'.$grade->student_id => $grade->score === null ? null : (float) $grade->score,
        ])->all();
        $publishedGrades = $latestPublication?->items->mapWithKeys(fn ($grade) => [
            $grade->plan_item_id.'_'.$grade->student_id => $grade->score === null ? null : (float) $grade->score,
        ])->all() ?? [];

        $draftRasgos = $plan->rasgos->mapWithKeys(fn ($rasgo) => [
            $rasgo->student_id => $rasgo->rasgos_score === null ? null : (int) $rasgo->rasgos_score,
        ])->all();
        $publishedRasgos = $latestPublication?->rasgos->mapWithKeys(fn ($rasgo) => [
            $rasgo->student_id => $rasgo->rasgos_score === null ? null : (int) $rasgo->rasgos_score,
        ])->all() ?? [];

        $hasDraftContent = $grades->isNotEmpty() || $plan->rasgos->isNotEmpty();
        $canPublish = $latestPublication === null
            ? $hasDraftContent
            : $draftGrades !== $publishedGrades || $draftRasgos !== $publishedRasgos;

        return [
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'matter_name' => $plan->matter?->name,
                'school_lapse_label' => $this->lapseLabel($plan->schoolLapse),
                'lapse_label' => $this->momentLabel($plan->lapse),
                'course_name' => $plan->course?->name,
                'section_name' => $plan->section?->name,
                'rasgos_points' => $planRasgosPoints,
            ],
            'items' => $items,
            'units' => $units,
            'students' => $studentsData,
            'grade_state' => [
                'published_version_id' => $latestPublication?->id,
                'published_at' => $latestPublication?->published_at?->toISOString(),
                'can_publish' => $canPublish,
            ],
        ];
    }

    public function saveGrades(int $planId, array $grades, array $rasgos = []): int
    {
        $plan = EvaluationPlan::with(['items', 'rasgos'])->findOrFail($planId);

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

        $maxRasgos = (int) $plan->rasgos_points;
        if ($maxRasgos > 0) {
            foreach ($rasgos as $rasgo) {
                $studentId = (int) ($rasgo['student_id'] ?? 0);

                if (! $studentIds->contains($studentId)) {
                    continue;
                }

                $raw = $rasgo['rasgos_score'] ?? null;
                $score = ($raw === null || $raw === '') ? null : (int) $raw;

                if ($score !== null) {
                    $score = max(0, min($maxRasgos, $score));
                }

                StudentPlanRasgo::updateOrCreate(
                    ['evaluation_plan_id' => $planId, 'student_id' => $studentId],
                    ['rasgos_score' => $score]
                );

                $saved++;
            }
        }

        return $saved;
    }

    public function publishGrades(int $planId, int $teacherId): StudentGradePublication
    {
        return DB::transaction(function () use ($planId, $teacherId) {
            $plan = EvaluationPlan::with('items')
                ->where('id', $planId)
                ->where('user_id', $teacherId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($plan->status !== 'approved') {
                throw new \RuntimeException('El plan debe estar aprobado antes de publicar las notas.');
            }

            $version = ((int) StudentGradePublication::where('evaluation_plan_id', $plan->id)
                ->lockForUpdate()
                ->max('version')) + 1;

            $publication = StudentGradePublication::create([
                'evaluation_plan_id' => $plan->id,
                'published_by' => $teacherId,
                'version' => $version,
                'published_at' => now(),
            ]);

            $itemIds = $plan->items->pluck('id');
            StudentGrade::whereIn('plan_item_id', $itemIds)
                ->get()
                ->each(fn ($grade) => StudentGradePublicationItem::create([
                    'publication_id' => $publication->id,
                    'plan_item_id' => $grade->plan_item_id,
                    'student_id' => $grade->student_id,
                    'score' => $grade->score,
                ]));

            StudentPlanRasgo::where('evaluation_plan_id', $plan->id)
                ->get()
                ->each(fn ($rasgo) => StudentGradePublicationRasgo::create([
                    'publication_id' => $publication->id,
                    'student_id' => $rasgo->student_id,
                    'rasgos_score' => $rasgo->rasgos_score,
                ]));

            return $publication;
        });
    }

    public function publishedScoresForStudent(EvaluationPlan $plan, int $studentId): array
    {
        $publication = StudentGradePublication::where('evaluation_plan_id', $plan->id)
            ->latest('version')
            ->first();

        if (! $publication) {
            return [];
        }

        return StudentGradePublicationItem::where('publication_id', $publication->id)
            ->where('student_id', $studentId)
            ->pluck('score', 'plan_item_id')
            ->map(fn ($score) => $score !== null ? (float) $score : null)
            ->all();
    }

    public function publishedDefinitiveForStudent(EvaluationPlan $plan, int $studentId): ?float
    {
        $scores = $this->publishedScoresForStudent($plan, $studentId);
        $items = $plan->items->map(fn ($item) => [
            'id' => $item->id,
            'percentage' => (float) $item->percentage,
        ])->all();

        $publication = StudentGradePublication::where('evaluation_plan_id', $plan->id)
            ->latest('version')
            ->first();
        $rasgos = $publication
            ? StudentGradePublicationRasgo::where('publication_id', $publication->id)
                ->where('student_id', $studentId)
                ->value('rasgos_score')
            : null;

        return $this->computeDefinitive($items, $scores, (int) $plan->rasgos_points > 0 ? $rasgos : 0);
    }

    public function computeDefinitive(array $items, array $scores, ?float $rasgos = 0): ?float
    {
        $total = 0.0;

        foreach ($items as $item) {
            $score = $scores[$item['id']] ?? null;

            if ($score === null) {
                return null;
            }

            $total += (float) $score * ((float) $item['percentage'] / 100);
        }

        if ($rasgos === null) {
            return null;
        }

        return round($total + (float) $rasgos, 2);
    }

    public function definitiveForStudent(EvaluationPlan $plan, int $studentId): ?float
    {
        $scores = [];
        $items = [];

        foreach ($plan->items as $item) {
            $items[] = ['id' => $item->id, 'percentage' => (float) $item->percentage];
            $scores[$item->id] = $item->grades->firstWhere('student_id', $studentId)?->score ?? null;
        }

        $rasgos = StudentPlanRasgo::where('evaluation_plan_id', $plan->id)
            ->where('student_id', $studentId)
            ->value('rasgos_score');

        return $this->computeDefinitive($items, $scores, (int) $plan->rasgos_points > 0 ? $rasgos : 0);
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
