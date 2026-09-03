<?php

namespace App\Services;

use App\Models\EvaluationPlan;
use App\Models\Representative;
use App\Models\SchoolLapse;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;

class RepresentativeService
{
    private StudentGradeService $gradeService;

    public function __construct()
    {
        $this->gradeService = new StudentGradeService;
    }

    private const SCHOOL_MONTHS = [
        'september',
        'october',
        'november',
        'december',
        'january',
        'february',
        'march',
        'april',
        'may',
        'june',
        'july',
        'august',
    ];

    public function getRepresentativeForUser(User $user): ?Representative
    {
        return Representative::where('user_id', $user->id)->first();
    }

    public function getStudents(User $user)
    {
        $representative = $this->getRepresentativeForUser($user);

        if (! $representative) {
            return collect();
        }

        return Student::where('representative_id', $representative->id)
            ->where('status', '!=', 0)
            ->with('course', 'section', 'balances.schoolLapse')
            ->get();
    }

    public function home(User $user): array
    {
        $students = $this->getStudents($user);

        return [
            'students' => $students->map(fn ($student) => $this->formatStudent($student))->values(),
            'total_children' => $students->count(),
        ];
    }

    public function misHijos(User $user): array
    {
        $students = $this->getStudents($user)
            ->load('course.matters');

        $activeLapse = SchoolLapse::where('status', 1)->with('lapses')->first();
        $currentLapse = $this->currentLapse($activeLapse);

        return [
            'students' => $students->map(function ($student) use ($activeLapse, $currentLapse) {
                return array_merge($this->formatStudent($student), [
                    'subjects' => $this->formatSubjects($student, $activeLapse, $currentLapse),
                ]);
            })->values(),
        ];
    }

    private function currentLapse($schoolLapse)
    {
        if (! $schoolLapse || $schoolLapse->lapses->isEmpty()) {
            return null;
        }

        $today = Carbon::now()->toDateString();

        return $schoolLapse->lapses->first(fn ($l) => $today >= $l->start && $today <= $l->end)
            ?? $schoolLapse->lapses->sortByDesc('number')->first();
    }

    private function formatSubjects($student, $activeLapse, $currentLapse): array
    {
        $matters = $student->course?->matters ?? collect();

        if ($matters->isEmpty() || ! $activeLapse || ! $currentLapse) {
            return [];
        }

        $plans = EvaluationPlan::with(['teacher', 'lapse', 'schoolLapse', 'items'])
            ->where('course_id', $student->course_id)
            ->where('school_lapse_id', $activeLapse->id)
            ->where('lapse_id', $currentLapse->id)
            ->where('status', 'approved')
            ->get();

        $plansByMatter = $plans->groupBy('matter_id');

        return $matters->map(function ($matter) use ($plansByMatter, $currentLapse, $student) {
            $plan = $plansByMatter->get($matter->id)?->first();

            if (! $plan) {
                return [
                    'matter_id' => $matter->id,
                    'matter_name' => $matter->name,
                    'status' => 'sin_plan',
                    'status_label' => 'Sin plan',
                    'definitive' => null,
                    'lapse_label' => $this->momentLabel($currentLapse),
                    'plan' => null,
                ];
            }

            $definitive = $this->gradeService->publishedDefinitiveForStudent($plan, $student->id);

            $status = $definitive === null
                ? 'en_curso'
                : ($definitive >= StudentGradeService::PASSING_SCORE ? 'aprobada' : 'reprobada');

            return [
                'matter_id' => $matter->id,
                'matter_name' => $matter->name,
                'status' => $status,
                'status_label' => match ($status) {
                    'aprobada' => 'Aprobada',
                    'reprobada' => 'Reprobada',
                    default => 'En curso',
                },
                'definitive' => $definitive,
                'lapse_label' => $this->momentLabel($currentLapse),
                'plan' => $this->formatSubjectPlan($plan, $student->id),
            ];
        })->values()->all();
    }

    private function formatSubjectPlan(EvaluationPlan $plan, int $studentId): array
    {
        $scores = $this->gradeService->publishedScoresForStudent($plan, $studentId);
        $items = $plan->items->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'percentage' => (float) $item->percentage,
                'date' => $item->date,
                'score' => null,
            ];
        })->values()->map(function ($item) use ($scores) {
            $item['score'] = $scores[$item['id']] ?? null;
            return $item;
        });

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'teacher_name' => $plan->teacher ? ($plan->teacher->name.' '.$plan->teacher->last_name) : null,
            'lapse_label' => $this->momentLabel($plan->lapse),
            'school_lapse_label' => $plan->schoolLapse
                ? Carbon::parse($plan->schoolLapse->start)->year.' - '.Carbon::parse($plan->schoolLapse->end)->year
                : null,
            'items' => $items,
        ];
    }

    private function momentLabel($lapse): ?string
    {
        if (! $lapse || ! $lapse->number) {
            return null;
        }

        $ordinals = [1 => '1er', 2 => '2do', 3 => '3er'];

        return ($ordinals[$lapse->number] ?? $lapse->number).' Momento';
    }

    public function misPagos(User $user): array
    {
        $currentLapse = SchoolLapse::where('status', 1)->first();

        $students = $this->getStudents($user)
            ->load(['balances.schoolLapse', 'balances.balancePayments.payment.accountPayment.method']);

        return [
            'students' => $students->map(function ($student) use ($currentLapse) {
                $visibleBalances = $student->balances
                    ->filter(function ($balance) use ($currentLapse) {
                        $isCurrent = $currentLapse && (int) $balance->school_lapse_id === (int) $currentLapse->id;
                        $hasDebt = $this->calculateDebt($balance) > 0;

                        return $isCurrent || $hasDebt;
                    })
                    ->values();

                return array_merge($this->formatStudent($student), [
                    'balances' => $visibleBalances->map(function ($balance) {
                        return [
                            'id' => $balance->id,
                            'status' => $balance->status,
                            'school_lapse' => $balance->schoolLapse,
                            'inscription' => $balance->inscription,
                            'inscription_status' => $balance->inscription_status,
                            'months' => collect(self::SCHOOL_MONTHS)->mapWithKeys(fn ($month) => [
                                $month => $balance->$month,
                                $month.'_status' => $balance->{$month.'_status'},
                            ]),
                            'total_debt' => $this->calculateDebt($balance),
                            'total_income' => $balance->balancePayments->sum('amount'),
                            'balance_payments' => $balance->balancePayments
                                ->map(fn ($bp) => [
                                    'amount' => $bp->amount,
                                    'month' => $bp->month,
                                    'is_inscription' => $bp->is_inscription,
                                    'date' => $bp->payment?->date,
                                    'reference' => $bp->payment?->reference,
                                    'method' => $bp->payment?->accountPayment?->method?->name ?? null,
                                ])
                                ->values(),
                        ];
                    })->values(),
                ]);
            })->values(),
        ];
    }

    public function formatStudent($student): array
    {
        return [
            'id' => $student->id,
            'name' => $student->name,
            'last_name' => $student->last_name,
            'ci' => $student->ci,
            'sex' => $student->sex,
            'course' => $student->course?->name,
            'section' => $student->section?->name,
            'is_exempt' => $student->is_exempt,
            'exemption_percentage' => $student->exemption_percentage,
        ];
    }

    private function calculateDebt($balance): float
    {
        $debt = 0;
        if ($balance->inscription < 0) {
            $debt += abs($balance->inscription);
        }
        foreach (self::SCHOOL_MONTHS as $month) {
            if ($balance->$month < 0) {
                $debt += abs($balance->$month);
            }
        }

        return (float) $debt;
    }
}
