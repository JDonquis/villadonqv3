<?php

namespace App\Services;

use App\Models\Course;
use App\Models\EvaluationPlan;
use App\Models\Inscription;
use App\Models\MainConfig;
use App\Models\Matter;
use App\Models\SchoolLapse;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\StudentGradePublication;
use App\Models\StudentGradePublicationItem;
use App\Models\StudentGradePublicationRasgo;
use App\Models\StudentPlanRasgo;
use App\Support\EducationLevel;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DocumentService
{
    private const PASSING_SCORE = 10;

    private StudentGradeService $gradeService;

    private EvaluationPlanService $planService;

    public function __construct()
    {
        $this->gradeService = new StudentGradeService;
        $this->planService = new EvaluationPlanService;
    }

    public function filterOptions(): array
    {
        return [
            'school_lapses' => $this->planService->getSchoolLapses(),
            'courses' => $this->planService->getCourses(),
            'current_school_lapse_id' => $this->planService->currentSchoolLapseId(),
        ];
    }

    public function studentOptions(): array
    {
        return Student::query()
            ->where('status', '!=', 0)
            ->with(['course:id,name', 'section:id,name'])
            ->orderBy('last_name')
            ->orderBy('name')
            ->get()
            ->map(fn (Student $student) => [
                'id' => $student->id,
                'name' => trim($student->name.' '.$student->last_name),
                'last_name' => $student->last_name,
                'ci' => $student->ci,
                'document_type' => $student->document_type,
                'course_id' => $student->course_id,
                'course_name' => $student->course?->name,
                'section_name' => $student->section?->name,
            ])->values()->all();
    }

    public function getSabanaAcademica(?int $schoolLapseId, ?int $courseId, ?int $sectionId, ?int $lapseId): array
    {
        $schoolLapse = $this->resolveSchoolLapse($schoolLapseId);
        $course = $this->resolveCourse($courseId);
        $section = $this->resolveSection($sectionId);
        $lapseId = $lapseId ?: $this->activeLapseId($schoolLapse);

        $matters = $this->resolveMatters($course?->id, $schoolLapse?->id, $section?->id);
        $gradeIndex = $this->buildGradeIndex($schoolLapse?->id, $course?->id, $section?->id, $lapseId);
        $students = $this->studentsFor($course?->id, $section?->id);

        $studentsData = $students->map(function (Student $student) use ($matters, $gradeIndex, $lapseId) {
            $grades = [];
            $scores = [];

            foreach ($matters as $matter) {
                $score = $gradeIndex['by_matter'][$matter['id']][$student->id]['by_lapse'][$lapseId] ?? null;

                if ($score === null && $lapseId === null) {
                    $score = $gradeIndex['by_matter'][$matter['id']][$student->id]['annual'] ?? null;
                }

                $score = $score !== null ? round((float) $score, 2) : null;
                $grades[$matter['id']] = [
                    'score' => $score,
                    'approved' => $score !== null && $score >= self::PASSING_SCORE,
                ];

                if ($score !== null) {
                    $scores[] = $score;
                }
            }

            return [
                'id' => $student->id,
                'ci' => $student->ci,
                'name' => $student->name,
                'last_name' => $student->last_name,
                'full_name' => trim($student->last_name.' '.$student->name),
                'section_name' => $student->section?->name,
                'grades' => $grades,
                'average' => $scores ? round(array_sum($scores) / count($scores), 2) : null,
                'approved_count' => collect($scores)->filter(fn ($v) => $v >= self::PASSING_SCORE)->count(),
                'failed_count' => collect($scores)->filter(fn ($v) => $v < self::PASSING_SCORE)->count(),
            ];
        })->values();

        $studentsData = $this->rankStudents($studentsData);
        $matterStats = [];

        foreach ($matters as $matter) {
            $values = $studentsData
                ->map(fn ($student) => $student['grades'][$matter['id']]['score'] ?? null)
                ->filter(fn ($v) => $v !== null)
                ->values();

            $matterStats[$matter['id']] = [
                'average' => $values->isNotEmpty() ? round($values->avg(), 2) : null,
                'approved' => $values->filter(fn ($v) => $v >= self::PASSING_SCORE)->count(),
                'failed' => $values->filter(fn ($v) => $v < self::PASSING_SCORE)->count(),
            ];
        }

        return [
            'institution' => $this->institution(),
            'meta' => [
                'school_lapse_id' => $schoolLapse?->id,
                'school_lapse_label' => $this->schoolLapseLabel($schoolLapse),
                'course_id' => $course?->id,
                'course_name' => $course?->name,
                'section_id' => $section?->id,
                'section_name' => $section?->name,
                'lapse_id' => $lapseId,
                'lapse_label' => $this->momentLabel($schoolLapse?->lapses->firstWhere('id', $lapseId)),
                'generated_at' => Carbon::now()->translatedFormat('d/m/Y'),
                'generated_at_full' => Carbon::now()->translatedFormat('d \d\e F \d\e Y'),
            ],
            'matters' => $matters,
            'students' => $studentsData->values()->all(),
            'stats' => [
                'enrolled' => $studentsData->count(),
                'approved' => $studentsData->filter(fn ($s) => $s['average'] !== null && $s['average'] >= self::PASSING_SCORE)->count(),
                'failed' => $studentsData->filter(fn ($s) => $s['average'] !== null && $s['average'] < self::PASSING_SCORE)->count(),
                'average' => $studentsData->pluck('average')->filter(fn ($v) => $v !== null)->avg(),
            ],
            'matter_stats' => $matterStats,
        ];
    }

    public function getResumenFinal(?int $schoolLapseId, ?int $courseId, ?int $sectionId): array
    {
        $schoolLapse = $this->resolveSchoolLapse($schoolLapseId);
        $course = $this->resolveCourse($courseId);
        $section = $this->resolveSection($sectionId);

        $matters = $this->resolveMatters($course?->id, $schoolLapse?->id, $section?->id);
        $gradeIndex = $this->buildGradeIndex($schoolLapse?->id, $course?->id, $section?->id, null);
        $students = $this->studentsFor($course?->id, $section?->id);

        $studentsData = $students->map(function (Student $student) use ($matters, $gradeIndex) {
            $grades = [];
            $scores = [];

            foreach ($matters as $matter) {
                $score = $gradeIndex['by_matter'][$matter['id']][$student->id]['annual'] ?? null;
                $score = $score !== null ? round((float) $score, 2) : null;
                $grades[$matter['id']] = $score;

                if ($score !== null) {
                    $scores[] = $score;
                }
            }

            return [
                'id' => $student->id,
                'ci' => $student->ci,
                'document_type' => $student->document_type ?: 'V',
                'name' => $student->name,
                'last_name' => $student->last_name,
                'full_name' => trim($student->last_name.' '.$student->name),
                'sex' => $this->sexCode($student->sex),
                'birth_date' => $student->date_birth,
                'birth_day' => Carbon::parse($student->date_birth)->format('d'),
                'birth_month' => Carbon::parse($student->date_birth)->format('m'),
                'birth_year' => Carbon::parse($student->date_birth)->format('Y'),
                'birth_place' => $student->representative?->user?->city ?: $this->institution()['municipality'],
                'grades' => $grades,
                'average' => $scores ? round(array_sum($scores) / count($scores), 2) : null,
                'approved_count' => collect($scores)->filter(fn ($v) => $v >= self::PASSING_SCORE)->count(),
                'failed_count' => collect($scores)->filter(fn ($v) => $v < self::PASSING_SCORE)->count(),
            ];
        })->values()->all();

        $totals = [];
        foreach ($matters as $matter) {
            $values = collect($studentsData)->pluck('grades')->pluck($matter['id'])->filter(fn ($v) => $v !== null);
            $totals[$matter['id']] = [
                'enrolled' => count($studentsData),
                'absent' => 0,
                'approved' => $values->filter(fn ($v) => $v >= self::PASSING_SCORE)->count(),
                'failed' => $values->filter(fn ($v) => $v < self::PASSING_SCORE)->count(),
                'not_taken' => count($studentsData) - $values->count(),
            ];
        }

        return [
            'institution' => $this->institution(),
            'meta' => [
                'school_lapse_id' => $schoolLapse?->id,
                'school_lapse_label' => $this->schoolLapseLabel($schoolLapse),
                'course_id' => $course?->id,
                'course_name' => $course?->name,
                'course_year_label' => $course ? $this->courseYearLabel($course->id) : null,
                'section_id' => $section?->id,
                'section_name' => $section?->name,
                'plan_code' => $course?->plan_de_estudio_code,
                'plan_name' => $this->educationLevelLabel($course?->id),
                'generated_at' => Carbon::now()->translatedFormat('d/m/Y'),
                'generated_at_long' => $this->spanishDate(Carbon::now()),
                'enrolled' => count($studentsData),
                'total_students' => count($studentsData),
            ],
            'matters' => $matters,
            'students' => $studentsData,
            'totals' => $totals,
            'professors' => $this->professorsByMatter($matters, $schoolLapse?->id, $course?->id, $section?->id),
        ];
    }

    public function getCertificadoNotas(int $studentId): array
    {
        $student = Student::with(['course', 'section', 'representative.user', 'inscriptions.schoolLapse', 'inscriptions.course', 'inscriptions.section'])
            ->findOrFail($studentId);

        $inscriptions = $student->inscriptions
            ->sortBy(fn (Inscription $inscription) => $inscription->schoolLapse?->start ?? '0000-00-00')
            ->values();

        if ($inscriptions->isEmpty()) {
            $inscriptions = collect([new Inscription([
                'school_lapse_id' => $this->planService->currentSchoolLapseId(),
                'course_id' => $student->course_id,
                'section_id' => $student->section_id,
                'student_id' => $student->id,
            ])]);
        }

        $years = [];

        $latestFirst = $inscriptions->sortByDesc(fn (Inscription $inscription) => $inscription->schoolLapse?->start ?? '0000-00-00')->values();

        for ($yearNumber = 1; $yearNumber <= 5; $yearNumber++) {
            $inscription = $latestFirst->first(fn (Inscription $row) => $this->courseYearNumber($row->course_id) === $yearNumber);

            if (! $inscription) {
                $defaultCourseId = $this->courseIdForYear($yearNumber);
                $subjects = collect($this->defaultMatterNames($defaultCourseId))
                    ->map(fn ($name) => [
                        'name' => $name,
                        'score' => null,
                        'letters' => null,
                    ])
                    ->take($this->certificateSubjectLimit($yearNumber))
                    ->values()->all();

                $years[$yearNumber] = [
                    'number' => $yearNumber,
                    'label' => $this->ordinalYearLabel($yearNumber),
                    'has_data' => false,
                    'course_name' => null,
                    'plantel' => null,
                    'subjects' => $subjects,
                ];

                continue;
            }

            $schoolLapse = $inscription->schoolLapse ?? $this->resolveSchoolLapse($inscription->school_lapse_id);
            $course = $inscription->course ?? $this->resolveCourse($inscription->course_id);
            $section = $inscription->section ?? $this->resolveSection($inscription->section_id);
            $matters = $this->resolveMatters($course?->id, $schoolLapse?->id, $section?->id);
            $gradeIndex = $this->buildGradeIndex($schoolLapse?->id, $course?->id, $section?->id, null);

            $subjects = collect($matters)->map(function ($matter) use ($gradeIndex, $student) {
                $score = $gradeIndex['by_matter'][$matter['id']][$student->id]['annual'] ?? null;

                return [
                    'name' => $matter['name'],
                    'score' => $score !== null ? round((float) $score, 2) : null,
                    'letters' => $score !== null ? $this->numberToLetters((int) round($score)) : null,
                ];
            })->take($this->certificateSubjectLimit($yearNumber))->values()->all();

            $years[$yearNumber] = [
                'number' => $yearNumber,
                'label' => $this->ordinalYearLabel($yearNumber),
                'has_data' => true,
                'course_name' => $course?->name,
                'plantel' => $inscription->id ? $student->previous_school ?: $this->institution()['name'] : $this->institution()['name'],
                'school_lapse_label' => $this->schoolLapseLabel($schoolLapse),
                'year' => $schoolLapse?->end ? Carbon::parse($schoolLapse->end)->format('Y') : null,
                'subjects' => $subjects,
            ];
        }

        return [
            'institution' => $this->institution(),
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'last_name' => $student->last_name,
                'full_name' => trim($student->last_name.' '.$student->name),
                'ci' => $student->ci,
                'document_type' => $student->document_type ?: 'V',
                'birth_date' => $student->date_birth,
                'birth_date_label' => $this->spanishDate(Carbon::parse($student->date_birth)),
                'birth_day' => Carbon::parse($student->date_birth)->format('d'),
                'birth_month' => Carbon::parse($student->date_birth)->format('m'),
                'birth_year' => Carbon::parse($student->date_birth)->format('Y'),
                'birth_country' => 'VENEZUELA',
                'birth_state' => $student->representative?->user?->state ?: $this->institution()['federal_entity'],
                'birth_municipality' => $student->representative?->user?->city ?: $this->institution()['municipality'],
            ],
            'program' => [
                'label' => $this->educationLevelLabel($student->course_id),
                'code' => $student->course?->plan_de_estudio_code,
            ],
            'institutions' => $this->studiedInstitutions($student, $inscriptions),
            'years' => array_values($years),
            'generated_at' => Carbon::now()->translatedFormat('d/m/Y'),
            'generated_at_long' => $this->spanishDate(Carbon::now()),
            'director' => [
                'name' => $this->institution()['director_name'] ?: '—',
                'ci' => $this->institution()['director_ci'] ?: '—',
            ],
        ];
    }

    public function getRegistroTitulos(?int $schoolLapseId, ?int $courseId, ?int $sectionId, ?string $search = null): array
    {
        $schoolLapse = $this->resolveSchoolLapse($schoolLapseId);
        $course = $this->resolveCourse($courseId ?: 1);
        $section = $this->resolveSection($sectionId);

        $studentIds = collect();

        if ($schoolLapse) {
            $studentIds = Inscription::where('school_lapse_id', $schoolLapse->id)
                ->where('course_id', $course?->id)
                ->when($section, fn ($query) => $query->where('section_id', $section->id))
                ->pluck('student_id');
        }

        $students = Student::query()
            ->with(['course:id,name', 'section:id,name', 'representative.user:id,name,last_name,state,city'])
            ->where(function ($query) use ($studentIds, $course) {
                if ($studentIds->isNotEmpty()) {
                    $query->whereIn('id', $studentIds);
                } else {
                    $query->where('course_id', $course?->id ?: 1)->where('status', '!=', 0);
                }
            })
            ->when($section, fn ($query) => $query->where('section_id', $section->id))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('ci', 'like', '%'.$search.'%')
                        ->orWhereRaw("CONCAT(last_name, ' ', name) LIKE ?", ['%'.$search.'%']);
                });
            })
            ->orderBy('last_name')
            ->orderBy('name')
            ->get();

        $rows = $students->values()->map(function (Student $student, int $index) {
            $birthDate = Carbon::parse($student->date_birth);

            return [
                'id' => $student->id,
                'number' => $index + 1,
                'serial' => $student->title_serial,
                'ci' => $student->ci,
                'document_type' => $student->document_type ?: 'V',
                'full_name' => trim($student->last_name.' '.$student->name),
                'names' => $student->name,
                'last_name' => $student->last_name,
                'birth_day' => $birthDate->format('d'),
                'birth_month' => $birthDate->format('m'),
                'birth_year' => $birthDate->format('Y'),
                'birth_state_code' => $this->stateCode($student->representative?->user?->state),
                'birth_municipality' => $student->representative?->user?->city ?: $this->institution()['municipality'],
                'observations' => $student->title_observations,
                'section_name' => $student->section?->name,
                'course_name' => $student->course?->name,
            ];
        })->all();

        return [
            'institution' => $this->institution(),
            'meta' => [
                'school_lapse_id' => $schoolLapse?->id,
                'school_lapse_label' => $this->schoolLapseLabel($schoolLapse),
                'course_id' => $course?->id,
                'course_name' => $course?->name ?: '5to Año',
                'section_id' => $section?->id,
                'section_name' => $section?->name,
                'total' => count($rows),
                'generated_at' => Carbon::now()->translatedFormat('d/m/Y'),
                'generated_at_long' => $this->spanishDate(Carbon::now()),
                'year_label' => $course ? $this->courseYearLabel($course->id) : '5°',
            ],
            'students' => $rows,
            'authorities' => [
                'director_name' => $this->institution()['director_name'] ?: '',
                'director_ci' => $this->institution()['director_ci'] ?: '',
                'coordinator_name' => '',
                'coordinator_ci' => '',
                'official_name' => '',
                'official_ci' => '',
            ],
        ];
    }

    public function generateTitleSerials(int $schoolLapseId, int $courseId, ?int $sectionId, string $prefix, int $start): array
    {
        $prefix = strtoupper(preg_replace('/[^A-Za-z]/', '', $prefix) ?: 'AB');
        $data = $this->getRegistroTitulos($schoolLapseId, $courseId, $sectionId);
        $next = max(1, $start);
        $updated = 0;

        foreach ($data['students'] as $row) {
            if (! empty($row['serial'])) {
                continue;
            }

            $student = Student::find($row['id']);

            if (! $student) {
                continue;
            }

            do {
                $serial = $prefix.str_pad((string) $next, 7, '0', STR_PAD_LEFT);
                $next++;
                $exists = Student::where('title_serial', $serial)->exists();
            } while ($exists);

            $student->update([
                'title_serial' => $serial,
                'title_issued_at' => now()->toDateString(),
            ]);

            $updated++;
        }

        return ['updated' => $updated, 'next' => $next];
    }

    public function updateTitleSerial(int $studentId, ?string $serial, ?string $observations = null): Student
    {
        $student = Student::findOrFail($studentId);

        $serial = $serial !== null && trim($serial) !== '' ? strtoupper(trim($serial)) : null;

        if ($serial && Student::where('title_serial', $serial)->where('id', '!=', $student->id)->exists()) {
            throw new \RuntimeException('El serial indicado ya está asignado a otro estudiante.');
        }

        $student->update([
            'title_serial' => $serial,
            'title_issued_at' => $serial ? ($student->title_issued_at ?: now()->toDateString()) : null,
            'title_observations' => $observations,
        ]);

        return $student;
    }

    private function buildGradeIndex(?int $schoolLapseId, ?int $courseId, ?int $sectionId, ?int $lapseId): array
    {
        $index = ['by_matter' => [], 'plans' => collect()];

        if (! $schoolLapseId || ! $courseId) {
            return $index;
        }

        $plans = EvaluationPlan::with(['items', 'matter', 'teacher', 'section'])
            ->where('school_lapse_id', $schoolLapseId)
            ->where('course_id', $courseId)
            ->when($sectionId, fn ($query) => $query->where('section_id', $sectionId))
            ->when($lapseId, fn ($query) => $query->where('lapse_id', $lapseId))
            ->where('status', 'approved')
            ->get();

        if ($plans->isEmpty()) {
            return $index;
        }

        $index['plans'] = $plans;

        $planIds = $plans->pluck('id');
        $itemIds = $plans->flatMap(fn ($plan) => $plan->items->pluck('id'))->unique()->values();

        $latestPublications = StudentGradePublication::whereIn('evaluation_plan_id', $planIds)
            ->get()
            ->sortBy('version')
            ->groupBy('evaluation_plan_id')
            ->map(fn (Collection $group) => $group->last());

        $publicationIds = $latestPublications->pluck('id');

        $publicationItems = StudentGradePublicationItem::whereIn('publication_id', $publicationIds)
            ->get()
            ->groupBy(fn ($item) => $item->publication_id.'_'.$item->student_id);

        $publicationRasgos = StudentGradePublicationRasgo::whereIn('publication_id', $publicationIds)
            ->get()
            ->keyBy(fn ($rasgo) => $rasgo->publication_id.'_'.$rasgo->student_id);

        $draftGrades = StudentGrade::whereIn('plan_item_id', $itemIds)
            ->get()
            ->groupBy(fn ($grade) => $grade->student_id.'_'.$grade->plan_item_id);

        $draftRasgos = StudentPlanRasgo::whereIn('evaluation_plan_id', $planIds)
            ->get()
            ->keyBy(fn ($rasgo) => $rasgo->evaluation_plan_id.'_'.$rasgo->student_id);

        foreach ($plans as $plan) {
            $publication = $latestPublications->get($plan->id);
            $itemDefinitions = $plan->items->map(fn ($item) => [
                'id' => $item->id,
                'percentage' => (float) $item->percentage,
            ])->all();

            $studentIds = collect();

            if ($publication) {
                $studentIds = $studentIds->merge(
                    StudentGradePublicationItem::where('publication_id', $publication->id)->pluck('student_id')
                );
            }

            $planItemIds = $plan->items->pluck('id')->map(fn ($id) => (int) $id)->all();

            foreach ($draftGrades as $key => $rows) {
                [$draftStudentId, $draftItemId] = array_pad(explode('_', $key), 2, 0);

                if (in_array((int) $draftItemId, $planItemIds, true)) {
                    $studentIds->push((int) $draftStudentId);
                }
            }

            $studentIds = $studentIds
                ->merge($draftRasgos->keys()
                    ->filter(fn ($key) => str_starts_with($key, $plan->id.'_'))
                    ->map(fn ($key) => (int) explode('_', $key)[1]))
                ->filter()
                ->unique()
                ->values();

            foreach ($studentIds as $studentId) {
                $publishedScores = [];
                $draftScores = [];
                $hasPublishedRows = false;

                if ($publication) {
                    $rows = $publicationItems->get($publication->id.'_'.$studentId, collect());
                    $hasPublishedRows = $rows->isNotEmpty();
                    $publishedScores = $rows->pluck('score', 'plan_item_id')
                        ->map(fn ($score) => $score !== null ? (float) $score : null)
                        ->all();
                }

                foreach ($plan->items as $item) {
                    $draft = $draftGrades->get($studentId.'_'.$item->id)?->first();
                    $draftScores[$item->id] = $draft?->score !== null ? (float) $draft->score : null;
                }

                $scores = $hasPublishedRows ? $publishedScores : $draftScores;
                $rasgos = $publication
                    ? $publicationRasgos->get($publication->id.'_'.$studentId)?->rasgos_score
                    : $draftRasgos->get($plan->id.'_'.$studentId)?->rasgos_score;

                $definitive = $this->gradeService->computeDefinitive(
                    $itemDefinitions,
                    $scores,
                    (int) $plan->rasgos_points > 0 ? $rasgos : 0
                );

                if ($definitive === null) {
                    continue;
                }

                $matterId = (int) $plan->matter_id;

                if (! isset($index['by_matter'][$matterId][$studentId])) {
                    $index['by_matter'][$matterId][$studentId] = [
                        'by_lapse' => [],
                        'moments' => [],
                        'annual' => null,
                    ];
                }

                $index['by_matter'][$matterId][$studentId]['by_lapse'][$plan->lapse_id] = $definitive;
                $index['by_matter'][$matterId][$studentId]['moments'][$plan->lapse_id] = $definitive;
            }
        }

        foreach ($index['by_matter'] as $matterId => $studentRows) {
            foreach ($studentRows as $studentId => $row) {
                $values = collect($row['moments'])->filter(fn ($value) => $value !== null)->values();
                $index['by_matter'][$matterId][$studentId]['annual'] = $values->isNotEmpty() ? round($values->avg(), 2) : null;
            }
        }

        return $index;
    }

    private function studentsFor(?int $courseId, ?int $sectionId): Collection
    {
        return Student::with(['section:id,name', 'representative.user:id,name,last_name,state,city'])
            ->where('status', '!=', 0)
            ->when($courseId, fn ($query) => $query->where('course_id', $courseId))
            ->when($sectionId, fn ($query) => $query->where('section_id', $sectionId))
            ->orderBy('last_name')
            ->orderBy('name')
            ->get();
    }

    private function resolveMatters(?int $courseId, ?int $schoolLapseId, ?int $sectionId): array
    {
        $matters = collect();

        if ($courseId && $schoolLapseId) {
            $matters = EvaluationPlan::with('matter')
                ->where('school_lapse_id', $schoolLapseId)
                ->where('course_id', $courseId)
                ->when($sectionId, fn ($query) => $query->where('section_id', $sectionId))
                ->where('status', 'approved')
                ->get()
                ->pluck('matter')
                ->filter()
                ->unique('id')
                ->values();
        }

        if ($matters->isEmpty() && $courseId) {
            $matters = Course::find($courseId)?->matters()->orderBy('name')->get() ?? collect();
        }

        if ($matters->isEmpty()) {
            return collect($this->defaultMatterNames($courseId))
                ->values()
                ->map(fn ($name, $index) => [
                    'id' => -($index + 1),
                    'name' => $name,
                    'short' => $this->matterShort($name),
                ])->all();
        }

        return $matters->sortBy('name')->values()->map(fn (Matter $matter) => [
            'id' => $matter->id,
            'name' => $matter->name,
            'short' => $this->matterShort($matter->name),
        ])->all();
    }

    private function professorsByMatter(array $matters, ?int $schoolLapseId, ?int $courseId, ?int $sectionId): array
    {
        $result = [];

        foreach ($matters as $matter) {
            $teacher = null;

            if ($schoolLapseId && $courseId) {
                $teacher = EvaluationPlan::with('teacher')
                    ->where('school_lapse_id', $schoolLapseId)
                    ->where('course_id', $courseId)
                    ->where('matter_id', $matter['id'])
                    ->when($sectionId, fn ($query) => $query->where('section_id', $sectionId))
                    ->where('status', 'approved')
                    ->first()?->teacher;
            }

            if (! $teacher && $matter['id'] > 0) {
                $teacher = Matter::with('teachers')->find($matter['id'])?->teachers->first();
            }

            $result[$matter['id']] = [
                'name' => $teacher ? trim($teacher->name.' '.$teacher->last_name) : '',
                'ci' => $teacher?->ci ? ($teacher->document_type ?: 'V').'-'.$teacher->ci : '',
            ];
        }

        return $result;
    }

    private function rankStudents(Collection $students): Collection
    {
        $sorted = $students->sortByDesc(fn ($student) => $student['average'] ?? -1)->values();
        $position = 0;
        $previous = null;

        return $sorted->map(function ($student, $index) use (&$position, &$previous) {
            $average = $student['average'];

            if ($average === null) {
                $student['position'] = null;

                return $student;
            }

            if ($previous === null || $average != $previous) {
                $position = $index + 1;
                $previous = $average;
            }

            $student['position'] = $position;

            return $student;
        });
    }

    private function studiedInstitutions(Student $student, Collection $inscriptions): array
    {
        $rows = [];

        foreach ($inscriptions as $index => $inscription) {
            $rows[] = [
                'name' => $index === 0 && $student->previous_school ? $student->previous_school : $this->institution()['name'],
                'locality' => $this->institution()['municipality'],
                'state_code' => $this->stateCode($this->institution()['federal_entity']),
            ];
        }

        if (empty($rows)) {
            $rows[] = [
                'name' => $student->previous_school ?: $this->institution()['name'],
                'locality' => $this->institution()['municipality'],
                'state_code' => $this->stateCode($this->institution()['federal_entity']),
            ];
        }

        return array_slice($rows, 0, 5);
    }

    private function institution(): array
    {
        $config = MainConfig::first();

        return [
            'name' => $config?->name ?? 'INSTITUCIÓN EDUCATIVA',
            'code' => $config?->code ?: 'PD00011114',
            'rif' => $config?->rif ? strtoupper($config->rif) : null,
            'address' => $config?->address,
            'phone' => $config?->phone_number,
            'email' => $config?->email,
            'motto' => $config?->motto,
            'municipality' => $config?->municipality ?: '',
            'federal_entity' => $config?->federal_entity ?: '',
            'cdcee' => $config?->cdcee ?: $config?->federal_entity,
            'director_name' => $config?->director_name,
            'director_ci' => $config?->director_ci,
            'logo' => $this->logoDataUri($config?->entity_shield),
        ];
    }

    private function logoDataUri(?string $shield): ?string
    {
        if (! $shield) {
            return null;
        }

        $path = public_path('storage/'.$shield);

        if (! is_file($path)) {
            return null;
        }

        $mime = @mime_content_type($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
    }

    private function resolveSchoolLapse(?int $schoolLapseId): ?SchoolLapse
    {
        if ($schoolLapseId) {
            $lapse = SchoolLapse::with('lapses')->find($schoolLapseId);

            if ($lapse) {
                return $lapse;
            }
        }

        return SchoolLapse::with('lapses')
            ->where('status', 1)
            ->first()
            ?? SchoolLapse::with('lapses')->orderByDesc('start')->first();
    }

    private function resolveCourse(?int $courseId): ?Course
    {
        if ($courseId) {
            return Course::find($courseId);
        }

        return Course::orderBy('id')->first();
    }

    private function resolveSection(?int $sectionId): ?Section
    {
        return $sectionId ? Section::find($sectionId) : null;
    }

    private function activeLapseId(?SchoolLapse $schoolLapse): ?int
    {
        if (! $schoolLapse) {
            return null;
        }

        $today = Carbon::now()->toDateString();
        $current = $schoolLapse->lapses->first(fn ($lapse) => $lapse->start && $lapse->end && $today >= $lapse->start && $today <= $lapse->end);

        return $current?->id ?? $schoolLapse->lapses->sortByDesc('number')->first()?->id;
    }

    private function schoolLapseLabel(?SchoolLapse $schoolLapse): ?string
    {
        if (! $schoolLapse || ! $schoolLapse->start || ! $schoolLapse->end) {
            return null;
        }

        return Carbon::parse($schoolLapse->start)->year.' - '.Carbon::parse($schoolLapse->end)->year;
    }

    private function momentLabel($lapse): ?string
    {
        if (! $lapse || ! $lapse->number) {
            return null;
        }

        return $this->ordinal($lapse->number).' Momento';
    }

    private function courseYearNumber(?int $courseId): int
    {
        return match ((int) $courseId) {
            5 => 1,
            4 => 2,
            3 => 3,
            2 => 4,
            1 => 5,
            default => 0,
        };
    }

    private function certificateSubjectLimit(int $year): int
    {
        return match ($year) {
            1, 2 => 7,
            3, 4 => 8,
            5 => 10,
            default => 10,
        };
    }

    private function courseIdForYear(int $year): int
    {
        return match ($year) {
            1 => 5,
            2 => 4,
            3 => 3,
            4 => 2,
            5 => 1,
            default => 1,
        };
    }

    private function courseYearLabel(?int $courseId): ?string
    {
        return match ($this->courseYearNumber($courseId)) {
            1 => '1er Año',
            2 => '2do Año',
            3 => '3er Año',
            4 => '4to Año',
            5 => '5to Año',
            default => null,
        };
    }

    private function ordinalYearLabel(int $year): string
    {
        return $this->ordinal($year).' Año';
    }

    private function ordinal(int $number): string
    {
        return match ($number) {
            1 => '1er',
            2 => '2do',
            3 => '3er',
            4 => '4to',
            5 => '5to',
            6 => '6to',
            7 => '7mo',
            8 => '8vo',
            9 => '9no',
            10 => '10mo',
            default => $number.'°',
        };
    }

    private function educationLevelLabel(?int $courseId): string
    {
        $level = EducationLevel::forCourseId($courseId);

        return match ($level) {
            EducationLevel::PREESCOLAR => 'EDUCACIÓN INICIAL',
            EducationLevel::PRIMARIA => 'EDUCACIÓN PRIMARIA',
            default => 'EDUCACIÓN MEDIA GENERAL',
        };
    }

    private function defaultMatterNames(?int $courseId): array
    {
        $level = EducationLevel::forCourseId($courseId);

        if ($level === EducationLevel::PREESCOLAR) {
            return ['Lenguaje y Comunicación', 'Matemáticas', 'Ciencias Naturales', 'Ciencias Sociales', 'Expresión Artística', 'Educación Física'];
        }

        if ($level === EducationLevel::PRIMARIA) {
            return ['Castellano', 'Matemáticas', 'Ciencias Naturales', 'Ciencias Sociales', 'Educación Física', 'Arte y Patrimonio', 'Inglés y otras Lenguas Extranjeras'];
        }

        $year = $this->courseYearNumber($courseId);

        return match ($year) {
            1, 2 => [
                'Castellano',
                'Inglés y otras Lenguas Extranjeras',
                'Matemáticas',
                'Educación Física',
                'Arte y Patrimonio',
                'Ciencias Naturales',
                'Geografía, Historia y Ciudadanía',
                'Orientación y Convivencia',
                'Participación en Grupos de Creación, Recreación y Producción',
            ],
            3 => [
                'Castellano',
                'Inglés y otras Lenguas Extranjeras',
                'Matemáticas',
                'Educación Física',
                'Física',
                'Química',
                'Biología',
                'Geografía, Historia y Ciudadanía',
                'Formación para la Soberanía Nacional',
            ],
            4 => [
                'Castellano',
                'Inglés y otras Lenguas Extranjeras',
                'Matemáticas',
                'Educación Física',
                'Física',
                'Química',
                'Biología',
                'Ciencias de la Tierra',
                'Geografía, Historia y Ciudadanía',
                'Formación para la Soberanía Nacional',
            ],
            default => [
                'Castellano',
                'Inglés y otras Lenguas Extranjeras',
                'Matemáticas',
                'Educación Física',
                'Física',
                'Química',
                'Biología',
                'Ciencias de la Tierra',
                'Geografía, Historia y Ciudadanía',
                'Formación para la Soberanía Nacional',
                'Orientación y Convivencia',
                'Participación en Grupos de Creación, Recreación y Producción',
            ],
        };
    }

    private function matterShort(string $name): string
    {
        $map = [
            'Castellano' => 'CAS',
            'Inglés y otras Lenguas Extranjeras' => 'ING',
            'Matemáticas' => 'MAT',
            'Educación Física' => 'ED',
            'Arte y Patrimonio' => 'ART',
            'Ciencias Naturales' => 'CS',
            'Ciencias Sociales' => 'CS',
            'Geografía, Historia y Ciudadanía' => 'GEO',
            'Orientación y Convivencia' => 'ORI',
            'Participación en Grupos de Creación, Recreación y Producción' => 'PAR',
            'Física' => 'FIS',
            'Química' => 'QUI',
            'Biología' => 'BIO',
            'Ciencias de la Tierra' => 'CT',
            'Formación para la Soberanía Nacional' => 'FSN',
        ];

        if (isset($map[$name])) {
            return $map[$name];
        }

        return mb_strtoupper(mb_substr(trim($name), 0, 3));
    }

    private function sexCode(?string $sex): string
    {
        $sex = mb_strtolower((string) $sex);

        return str_starts_with($sex, 'f') || str_contains($sex, 'femen') ? 'F' : 'M';
    }

    private function stateCode(?string $state): string
    {
        $state = mb_strtoupper(trim((string) $state));
        $codes = [
            'AMAZONAS' => 'AM',
            'ANZOATEGUI' => 'AN',
            'ANZOÁTEGUI' => 'AN',
            'APURE' => 'AP',
            'ARAGUA' => 'AR',
            'BARINAS' => 'BA',
            'BOLIVAR' => 'BO',
            'BOLÍVAR' => 'BO',
            'CARABOBO' => 'CA',
            'COJEDES' => 'CO',
            'DELTA AMACURO' => 'DA',
            'FALCON' => 'FA',
            'FALCÓN' => 'FA',
            'GUARICO' => 'GU',
            'GUÁRICO' => 'GU',
            'LARA' => 'LA',
            'MERIDA' => 'ME',
            'MÉRIDA' => 'ME',
            'MIRANDA' => 'MI',
            'MONAGAS' => 'MO',
            'NUEVA ESPARTA' => 'NE',
            'PORTUGUESA' => 'PO',
            'SUCRE' => 'SU',
            'TACHIRA' => 'TA',
            'TÁCHIRA' => 'TA',
            'TRUJILLO' => 'TR',
            'VARGAS' => 'VA',
            'YARACUY' => 'YA',
            'ZULIA' => 'ZU',
            'DISTRITO CAPITAL' => 'DC',
        ];

        return $codes[$state] ?? mb_substr($state, 0, 2);
    }

    private function spanishDate(Carbon $date): string
    {
        $months = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
            7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];

        return $date->day.' DE '.mb_strtoupper($months[(int) $date->month]).' DE '.$date->year;
    }

    private function numberToLetters(int $number): string
    {
        $words = [
            0 => 'CERO', 1 => 'UNO', 2 => 'DOS', 3 => 'TRES', 4 => 'CUATRO', 5 => 'CINCO',
            6 => 'SEIS', 7 => 'SIETE', 8 => 'OCHO', 9 => 'NUEVE', 10 => 'DIEZ',
            11 => 'ONCE', 12 => 'DOCE', 13 => 'TRECE', 14 => 'CATORCE', 15 => 'QUINCE',
            16 => 'DIECISÉIS', 17 => 'DIECISIETE', 18 => 'DIECIOCHO', 19 => 'DIECINUEVE', 20 => 'VEINTE',
        ];

        return $words[$number] ?? (string) $number;
    }
}
