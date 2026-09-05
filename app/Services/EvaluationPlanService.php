<?php

namespace App\Services;

use App\Enums\EvaluationPlanStatusEnum;
use App\Models\Course;
use App\Models\EvaluationPlan;
use App\Models\EvaluationPlanItem;
use App\Models\Lapse;
use App\Models\Matter;
use App\Models\Schedule;
use App\Models\SchoolLapse;
use App\Models\Section;
use Carbon\Carbon;

class EvaluationPlanService
{
    public function formatPlan(EvaluationPlan $plan): array
    {
        $itemsTotal = $plan->items->sum(fn ($item) => (float) $item->percentage);

        $units = $plan->items->groupBy(function ($item) {
            return $item->unit_name ?? 'Unidad 1';
        })->map(function ($group, $unitName) {
            $unitIndex = $group->first()->unit_number ?? 1;

            return [
                'id' => 'unit_'.($group->first()->unit_number ?? 1),
                'unit_number' => (int) $unitIndex,
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
        })->values()->all();

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'description' => $plan->description,
            'status' => $plan->status,
            'status_label' => EvaluationPlanStatusEnum::from($plan->status)->label(),
            'admin_note' => $plan->admin_note,
            'matter_id' => $plan->matter_id,
            'matter_name' => $plan->matter?->name,
            'school_lapse_id' => $plan->school_lapse_id,
            'school_lapse_label' => $this->lapseLabel($plan->schoolLapse),
            'lapse_id' => $plan->lapse_id,
            'lapse_number' => $plan->lapse?->number,
            'lapse_label' => $this->momentLabel($plan->lapse),
            'course_id' => $plan->course_id,
            'course_name' => $plan->course?->name,
            'section_id' => $plan->section_id,
            'section_name' => $plan->section?->name,
            'teacher_id' => $plan->user_id,
            'teacher_name' => $plan->teacher ? ($plan->teacher->name.' '.$plan->teacher->last_name) : null,
            'items' => $plan->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'percentage' => (float) $item->percentage,
                'date' => $item->date,
            ])->values(),
            'units' => $units,
            'rasgos_points' => (int) $plan->rasgos_points,
            'items_total' => round($itemsTotal, 2),
            'rasgos_percentage' => round((int) $plan->rasgos_points * 5, 2),
            'total_percentage' => round($itemsTotal + ((int) $plan->rasgos_points * 5), 2),
            'created_at' => $plan->created_at?->format('Y-m-d H:i'),
        ];
    }

    private function lapseLabel(?SchoolLapse $lapse): ?string
    {
        if (! $lapse) {
            return null;
        }

        return Carbon::parse($lapse->start)->year.' - '.Carbon::parse($lapse->end)->year;
    }

    public function momentLabel(?Lapse $lapse): ?string
    {
        if (! $lapse || ! $lapse->number) {
            return null;
        }

        $ordinals = [1 => '1er', 2 => '2do', 3 => '3er'];

        return ($ordinals[$lapse->number] ?? $lapse->number).' Momento';
    }

    public function getPlansForAdmin(array $filters): array
    {
        $query = EvaluationPlan::with(['matter', 'schoolLapse', 'lapse', 'course', 'section', 'teacher', 'items'])
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['school_lapse_id'])) {
            $query->where('school_lapse_id', $filters['school_lapse_id']);
        }

        if (! empty($filters['lapse_id'])) {
            $query->where('lapse_id', $filters['lapse_id']);
        }

        if (! empty($filters['course_id'])) {
            $query->where('course_id', $filters['course_id']);
        }

        if (! empty($filters['section_id'])) {
            $query->where('section_id', $filters['section_id']);
        }

        if (! empty($filters['matter_id'])) {
            $query->where('matter_id', $filters['matter_id']);
        }

        if (! empty($filters['teacher_id'])) {
            $query->where('user_id', $filters['teacher_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('matter', fn ($m) => $m->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('course', fn ($c) => $c->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('section', fn ($s) => $s->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('schoolLapse', fn ($sl) => $sl->where('start', 'like', "%{$search}%"))
                    ->orWhereHas('teacher', fn ($t) => $t->whereRaw(
                        "CONCAT(name, ' ', last_name) LIKE ?",
                        ["%{$search}%"]
                    ));
            });
        }

        return $query->get()->map(fn ($plan) => $this->formatPlan($plan))->values()->all();
    }

    public function getPlansForTeacher(int $teacherId, array $filters = []): array
    {
        $query = EvaluationPlan::with(['matter', 'schoolLapse', 'lapse', 'course', 'section', 'items'])
            ->where('user_id', $teacherId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->whereIn('status', [
                EvaluationPlanStatusEnum::Approved->value,
                EvaluationPlanStatusEnum::Pending->value,
                EvaluationPlanStatusEnum::Draft->value,
            ]);
        }

        $schoolLapseId = $filters['school_lapse_id'] ?? null;
        if (empty($schoolLapseId)) {
            $schoolLapseId = $this->currentSchoolLapseId();
        }
        if (! empty($schoolLapseId)) {
            $query->where('school_lapse_id', $schoolLapseId);
        }
        if (! empty($filters['lapse_id'])) {
            $query->where('lapse_id', $filters['lapse_id']);
        }
        if (! empty($filters['matter_id'])) {
            $query->where('matter_id', $filters['matter_id']);
        }

        return $query->orderByDesc('created_at')
            ->get()
            ->map(fn ($plan) => $this->formatPlan($plan))
            ->values()
            ->all();
    }

    /**
     * Días de la semana (1=lunes … 5=viernes) en que el profesor dicta la materia
     * en alguna de las secciones del horario del periodo (school_lapse_id + course_id).
     * Si no hay horario para esas secciones, o la materia no aparece en él, no se
     * restringe (restrict=false) y se permite cualquier día.
     */
    public function getAllowedWeekdays(array $params): array
    {
        $schoolLapseId = $params['school_lapse_id'] ?? null;
        $courseId = $params['course_id'] ?? null;
        $matterId = $params['matter_id'] ?? null;
        $teacherId = $params['teacher_id'] ?? null;

        $sectionIds = $params['section_ids'] ?? [];
        if (! is_array($sectionIds)) {
            $sectionIds = [$sectionIds];
        }
        $sectionIds = array_values(array_filter(array_map('intval', $sectionIds)));

        $allowedWeekdays = [];
        $hasSchedule = false;

        if (! empty($schoolLapseId) && ! empty($courseId) && ! empty($sectionIds)) {
            $schedules = Schedule::where('school_lapse_id', $schoolLapseId)
                ->where('course_id', $courseId)
                ->whereIn('section_id', $sectionIds)
                ->with(['classes' => fn ($q) => $q->select('schedule_id', 'day', 'matter_id', 'teacher_id')])
                ->get();

            $hasSchedule = $schedules->isNotEmpty();

            foreach ($schedules as $schedule) {
                foreach ($schedule->classes as $class) {
                    if ((int) $class->matter_id !== (int) $matterId) {
                        continue;
                    }
                    if ($teacherId !== null && (int) $class->teacher_id !== (int) $teacherId) {
                        continue;
                    }
                    $allowedWeekdays[] = (int) $class->day;
                }
            }

            $allowedWeekdays = array_values(array_unique($allowedWeekdays));
            sort($allowedWeekdays);
        }

        return [
            'restrict' => $hasSchedule && count($allowedWeekdays) > 0,
            'allowedWeekdays' => $allowedWeekdays,
        ];
    }

    public function createPlan(int $teacherId, array $data): EvaluationPlan
    {
        $sectionIds = $data['section_id'] ?? null;
        $unitsOrItems = $data['units'] ?? $data['items'] ?? [];

        // Normalize section ids when frontend sends 'all'
        if (is_array($sectionIds) && in_array('all', $sectionIds, true)) {
            $courseId = $data['course_id'] ?? null;
            if ($courseId) {
                $course = Course::find($courseId);
                $sectionIds = $course ? $course->section()->pluck('id')->map(fn ($v) => (string) $v)->values()->all() : [];
            } else {
                $sectionIds = [];
            }
        }

        // If multiple sections provided, create a plan per section (cloning behavior)
        if (is_array($sectionIds) && count($sectionIds) > 0) {
            $createdPlan = null;
            foreach (array_values($sectionIds) as $index => $sectionId) {
                $plan = EvaluationPlan::create([
                    'user_id' => $teacherId,
                    'matter_id' => $data['matter_id'],
                    'school_lapse_id' => $data['school_lapse_id'],
                    'lapse_id' => $data['lapse_id'] ?? null,
                    'course_id' => $data['course_id'] ?? null,
                    'section_id' => $sectionId,
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'rasgos_points' => (int) ($data['rasgos_points'] ?? 0),
                    'status' => $data['status'] ?? EvaluationPlanStatusEnum::Pending->value,
                    'approved_by' => $data['approved_by'] ?? null,
                    'approved_at' => $data['approved_at'] ?? null,
                ]);

                $this->registerCourseMatter($data['course_id'] ?? null, $data['matter_id']);
                $this->syncItems($plan, $unitsOrItems);

                if ($createdPlan === null) {
                    $createdPlan = $plan;
                }
            }

            return $createdPlan;
        }

        // Fallback single plan
        $plan = EvaluationPlan::create([
            'user_id' => $teacherId,
            'matter_id' => $data['matter_id'],
            'school_lapse_id' => $data['school_lapse_id'],
            'lapse_id' => $data['lapse_id'] ?? null,
            'course_id' => $data['course_id'] ?? null,
            'section_id' => is_array($sectionIds) ? ($sectionIds[0] ?? null) : $sectionIds,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'rasgos_points' => (int) ($data['rasgos_points'] ?? 0),
            'status' => $data['status'] ?? EvaluationPlanStatusEnum::Pending->value,
            'approved_by' => $data['approved_by'] ?? null,
            'approved_at' => $data['approved_at'] ?? null,
        ]);

        $this->registerCourseMatter($data['course_id'] ?? null, $data['matter_id']);
        $this->syncItems($plan, $unitsOrItems);

        return $plan;
    }

    public function updatePlan(EvaluationPlan $plan, array $data): EvaluationPlan
    {
        $sectionIds = $data['section_id'] ?? null;
        $unitsOrItems = $data['units'] ?? $data['items'] ?? [];

        // Normalize 'all' into actual section ids for the course
        if (is_array($sectionIds) && in_array('all', $sectionIds, true)) {
            $courseId = $data['course_id'] ?? null;
            if ($courseId) {
                $course = Course::find($courseId);
                $sectionIds = $course ? $course->section()->pluck('id')->map(fn ($v) => (string) $v)->values()->all() : [];
            } else {
                $sectionIds = [];
            }
        }

        if (is_array($sectionIds) && count($sectionIds) > 1) {
            // Update original plan to the first section, and create clones for the rest
            $first = array_shift($sectionIds);

            $plan->update([
                'matter_id' => $data['matter_id'],
                'school_lapse_id' => $data['school_lapse_id'],
                'lapse_id' => $data['lapse_id'] ?? null,
                'course_id' => $data['course_id'] ?? null,
                'section_id' => $first,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'rasgos_points' => (int) ($data['rasgos_points'] ?? 0),
                'status' => $data['status'] ?? EvaluationPlanStatusEnum::Pending->value,
                'admin_note' => null,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            $this->registerCourseMatter($data['course_id'] ?? null, $data['matter_id']);
            $this->syncItems($plan, $unitsOrItems);

            // Create clones for remaining sections
            foreach ($sectionIds as $sectionId) {
                $new = EvaluationPlan::create([
                    'user_id' => $plan->user_id,
                    'matter_id' => $data['matter_id'],
                    'school_lapse_id' => $data['school_lapse_id'],
                    'lapse_id' => $data['lapse_id'] ?? null,
                    'course_id' => $data['course_id'] ?? null,
                    'section_id' => $sectionId,
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'rasgos_points' => (int) ($data['rasgos_points'] ?? 0),
                    'status' => $data['status'] ?? EvaluationPlanStatusEnum::Pending->value,
                ]);

                $this->registerCourseMatter($data['course_id'] ?? null, $data['matter_id']);
                $this->syncItems($new, $unitsOrItems);
            }

            return $plan;
        }

        // Single section (or no array)
        $plan->update([
            'matter_id' => $data['matter_id'],
            'school_lapse_id' => $data['school_lapse_id'],
            'lapse_id' => $data['lapse_id'] ?? null,
            'course_id' => $data['course_id'] ?? null,
            'section_id' => is_array($sectionIds) ? ($sectionIds[0] ?? null) : $sectionIds,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'rasgos_points' => (int) ($data['rasgos_points'] ?? 0),
            'status' => $data['status'] ?? EvaluationPlanStatusEnum::Pending->value,
            'admin_note' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $this->registerCourseMatter($data['course_id'] ?? null, $data['matter_id']);
        $this->syncItems($plan, $unitsOrItems);

        return $plan;
    }

    private function registerCourseMatter($courseId, $matterId): void
    {
        if (! $courseId || ! $matterId) {
            return;
        }

        Course::find($courseId)?->matters()->syncWithoutDetaching([$matterId]);
    }

    private function flattenUnitsToItems(array $units): array
    {
        $items = [];

        foreach (array_values($units) as $unitIndex => $unit) {
            $unitTopics = is_array($unit['topics'] ?? null) ? $unit['topics'] : [];

            foreach (array_values($unitTopics) as $topicIndex => $topic) {
                $items[] = [
                    'unit_name' => $unit['name'] ?? null,
                    'unit_number' => $unit['unit_number'] ?? ($unitIndex + 1),
                    'name' => $topic['name'] ?? '',
                    'assessment_type' => $topic['assessment_type'] ?? null,
                    'percentage' => $topic['percentage'] ?? 0,
                    'points' => $topic['points'] ?? null,
                    'date' => $topic['scheduled_date'] ?? $topic['date'] ?? null,
                    'scheduled_date' => $topic['scheduled_date'] ?? $topic['date'] ?? null,
                    'description' => $topic['description'] ?? null,
                    'order' => ($unitIndex * 100) + $topicIndex,
                ];
            }
        }

        if ($items === []) {
            foreach (array_values($units) as $index => $item) {
                $items[] = [
                    'unit_name' => null,
                    'unit_number' => null,
                    'name' => $item['name'] ?? '',
                    'assessment_type' => $item['assessment_type'] ?? null,
                    'percentage' => $item['percentage'] ?? 0,
                    'points' => $item['points'] ?? null,
                    'date' => $item['date'] ?? null,
                    'scheduled_date' => $item['scheduled_date'] ?? $item['date'] ?? null,
                    'description' => $item['description'] ?? null,
                    'order' => $index,
                ];
            }
        }

        return $items;
    }

    private function syncItems(EvaluationPlan $plan, array $units): void
    {
        $plan->items()->delete();
        $items = $this->flattenUnitsToItems($units);

        foreach (array_values($items) as $index => $item) {
            EvaluationPlanItem::create([
                'evaluation_plan_id' => $plan->id,
                'unit_name' => $item['unit_name'],
                'unit_number' => $item['unit_number'],
                'name' => $item['name'],
                'assessment_type' => $item['assessment_type'],
                'percentage' => $item['percentage'],
                'points' => $item['points'],
                'date' => $item['date'] ?? null,
                'scheduled_date' => $item['scheduled_date'] ?? $item['date'] ?? null,
                'description' => $item['description'] ?? null,
                'order' => $item['order'] ?? $index,
            ]);
        }
    }

    public function deletePlan(EvaluationPlan $plan): void
    {
        $plan->delete();
    }

    public function approve(EvaluationPlan $plan, int $adminId): void
    {
        $plan->update([
            'status' => EvaluationPlanStatusEnum::Approved->value,
            'admin_note' => null,
            'approved_by' => $adminId,
            'approved_at' => now(),
        ]);
    }

    public function reject(EvaluationPlan $plan, int $adminId, ?string $note): void
    {
        $plan->update([
            'status' => EvaluationPlanStatusEnum::Rejected->value,
            'admin_note' => $note,
            'approved_by' => $adminId,
            'approved_at' => now(),
        ]);
    }

    public function getMatters(): array
    {
        return Matter::orderBy('name')->get()->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
        ])->values()->all();
    }

    public function getCourses(): array
    {
        return Course::with('section')->orderBy('id')->get()->map(function ($c) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'sections' => $c->section
                    ->sortBy('name')
                    ->values()
                    ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])
                    ->values()
                    ->all(),
            ];
        })->values()->all();
    }

    public function getSections(): array
    {
        return Section::orderBy('name')->get()->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
        ])->values()->all();
    }

    public function getSchoolLapses(): array
    {
        return SchoolLapse::orderByDesc('start')->with('lapses')->get()->map(function ($l) {
            return [
                'id' => $l->id,
                'label' => $this->lapseLabel($l),
                'is_active' => (bool) $l->status,
                'lapses' => $l->lapses->sortBy('number')->values()->map(fn ($lap) => [
                    'id' => $lap->id,
                    'number' => $lap->number,
                    'label' => $this->momentLabel($lap),
                    'start' => $lap->start,
                    'end' => $lap->end,
                ]),
            ];
        })->values()->all();
    }

    public function getActiveLapseId(): ?int
    {
        $schoolLapse = SchoolLapse::where('status', 1)->with('lapses')->first();

        if (! $schoolLapse) {
            return null;
        }

        $today = Carbon::now()->toDateString();
        $current = $schoolLapse->lapses->first(fn ($lap) => $today >= $lap->start && $today <= $lap->end);

        if ($current) {
            return $current->id;
        }

        $last = $schoolLapse->lapses->sortByDesc('number')->first();

        return $last?->id;
    }

    public function currentSchoolLapseId(): ?int
    {
        $today = Carbon::now()->toDateString();
        $schoolLapses = SchoolLapse::orderByDesc('start')->with('lapses')->get();

        $byDate = $schoolLapses->first(function ($l) use ($today) {
            $ranges = $l->lapses
                ->filter(fn ($lap) => $lap->start && $lap->end)
                ->sortBy('start')
                ->values();
            if ($ranges->isEmpty()) {
                return false;
            }

            return $today >= $ranges->first()->start && $today <= $ranges->last()->end;
        });

        return $byDate?->id
            ?? $schoolLapses->firstWhere('status', 1)?->id
            ?? $schoolLapses->first()?->id;
    }
}
