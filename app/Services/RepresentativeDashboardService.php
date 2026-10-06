<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\EvaluationPlan;
use App\Models\EvaluationPlanItem;
use App\Models\MainConfig;
use App\Models\SchoolEvent;
use App\Models\SchoolLapse;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;

class RepresentativeDashboardService
{
    private const MONTHS = [
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

    private RepresentativeService $representativeService;
    private StudentGradeService $gradeService;
    private AttendanceService $attendanceService;

    public function __construct()
    {
        $this->representativeService = new RepresentativeService;
        $this->gradeService = new StudentGradeService;
        $this->attendanceService = new AttendanceService;
    }

    /**
     * Datos del panel del representante. Todo se limita a sus representados:
     * nunca se expone información global ni de terceros.
     */
    public function getDashboardData(User $user): array
    {
        // Sólo la administración puede ver información financiera. Cualquier
        // otro rol (representante, profesor) ve matrícula y datos académicos.
        $canSeeMoney = $this->canSeeMoney($user);

        $students = $this->representativeService->getStudents($user);

        if ($students->isNotEmpty()) {
            $students->load('course.matters', 'balances', 'charges.paymentConcept');
        }

        $activeLapse = SchoolLapse::where('status', 1)->with('lapses')->first()
            ?? SchoolLapse::orderByDesc('start')->with('lapses')->first();

        $config = MainConfig::first();
        $day = max(1, (int) ($config->day_of_monthly_payment ?? 1));
        $grace = max(0, (int) ($config->grace_period ?? 0));

        $studentIds = $students->pluck('id')->all();
        $attendance = $this->attendanceService->getAccumulatedForStudents($studentIds, $activeLapse?->id);

        $children = $students->map(function (Student $student) use ($activeLapse, $day, $grace, $attendance, $canSeeMoney) {
            $balance = $activeLapse
                ? $student->balances->firstWhere('school_lapse_id', $activeLapse->id)
                : null;

            return [
                'id' => $student->id,
                'name' => $student->name,
                'last_name' => $student->last_name,
                'ci' => $student->ci,
                'course' => $student->course?->name,
                'section' => $student->section?->name,
                'is_exempt' => (bool) $student->is_exempt,
                'account' => $canSeeMoney
                    ? $this->formatAccount($student, $balance, $activeLapse, $day, $grace)
                    : null,
                'academic' => $activeLapse ? $this->formatAcademic($student, $activeLapse) : ['average' => null, 'subjects' => [], 'evolution' => []],
                'attendance' => $attendance[$student->id] ?? [
                    'present' => 0, 'excused' => 0, 'absent' => 0, 'total' => 0, 'rate' => null,
                ],
                'upcoming' => [
                    'payments' => $canSeeMoney
                        ? $this->formatUpcomingPayments($student, $balance, $activeLapse, $day, $grace)
                        : [],
                    'evaluations' => $this->formatUpcomingEvaluations($student, $activeLapse),
                ],
            ];
        })->values()->all();

        return [
            'can_see_money' => $canSeeMoney,
            'children' => $children,
            'announcements' => $this->formatAnnouncements($students),
            'events' => $this->formatEvents($students),
            'period' => $activeLapse ? [
                'id' => $activeLapse->id,
                'label' => Carbon::parse($activeLapse->start)->year.' - '.Carbon::parse($activeLapse->end)->year,
            ] : null,
        ];
    }

    private function canSeeMoney(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        // Sólo el administrador total. Un administrador limitado (is_admin = 0)
        // no ve información financiera, igual que representantes y profesores.
        return (bool) $user->is_admin;
    }

    private function formatAccount(Student $student, $balance, ?SchoolLapse $lapse, int $day, int $grace): array
    {
        if (! $balance) {
            return ['debt' => 0.0, 'is_up_to_date' => true, 'next_due' => null];
        }

        $debt = round($balance->currentDebt(), 2);
        $installments = $this->unpaidInstallments($balance, $lapse, $day, $grace);

        $next = collect($installments)
            ->filter(fn ($i) => $i['date'])
            ->sortBy('date')
            ->first();

        return [
            'debt' => $debt,
            'is_up_to_date' => $debt <= 0,
            'next_due' => $next,
        ];
    }

    private function formatAcademic(Student $student, SchoolLapse $lapse): array
    {
        $lapses = $lapse->lapses->sortBy('number')->values();

        $plans = EvaluationPlan::where('course_id', $student->course_id)
            ->where('school_lapse_id', $lapse->id)
            ->where('status', 'approved')
            ->with(['matter', 'items', 'rasgos'])
            ->get()
            ->groupBy(fn ($plan) => $plan->matter_id.'_'.($plan->lapse_id ?? 'null'));

        $matters = $student->course?->matters ?? collect();

        $evolution = [];
        foreach ($lapses as $moment) {
            $evolution[$moment->number] = ['sum' => 0.0, 'count' => 0];
        }

        $subjects = [];

        foreach ($matters as $matter) {
            $values = [];

            foreach ($lapses as $moment) {
                $plan = $plans->get($matter->id.'_'.$moment->id)?->first();
                $definitive = $plan ? $this->gradeService->publishedDefinitiveForStudent($plan, $student->id) : null;

                if ($definitive !== null) {
                    $values[] = $definitive;
                    $evolution[$moment->number]['sum'] += $definitive;
                    $evolution[$moment->number]['count']++;
                }
            }

            $subjects[] = [
                'name' => $matter->name,
                'annual' => empty($values) ? null : round(array_sum($values) / count($values), 2),
            ];
        }

        $annuals = collect($subjects)->pluck('annual')->filter(fn ($v) => $v !== null)->values();

        $evolutionData = $lapses->map(function ($moment) use ($evolution) {
            $bucket = $evolution[$moment->number];

            return [
                'label' => $this->momentLabel($moment),
                'value' => $bucket['count'] > 0 ? round($bucket['sum'] / $bucket['count'], 2) : null,
            ];
        })->values()->all();

        return [
            'average' => $annuals->isEmpty() ? null : round($annuals->avg(), 2),
            'subjects' => $subjects,
            'evolution' => $evolutionData,
        ];
    }

    private function formatUpcomingPayments(Student $student, $balance, ?SchoolLapse $lapse, int $day, int $grace): array
    {
        $items = [];

        if ($balance) {
            $items = $this->unpaidInstallments($balance, $lapse, $day, $grace);
        }

        foreach ($student->charges ?? [] as $charge) {
            if ($charge->remaining() > 0) {
                $items[] = [
                    'label' => $charge->paymentConcept?->name ?? $charge->type,
                    'amount' => round($charge->remaining(), 2),
                    'date' => null,
                    'status' => $charge->status?->value ?? $charge->status,
                ];
            }
        }

        usort($items, function ($a, $b) {
            return strcmp($a['date'] ?? '9999-12-31', $b['date'] ?? '9999-12-31');
        });

        return array_slice($items, 0, 6);
    }

    private function unpaidInstallments($balance, ?SchoolLapse $lapse, int $day, int $grace): array
    {
        if (! $lapse) {
            return [];
        }

        $base = Carbon::parse($lapse->start)->startOfMonth();
        $today = Carbon::now();
        $items = [];

        if ($balance->inscription_status?->value !== 'paid' && $balance->inscription < 0) {
            $due = $base->copy()->day(min($day, $base->daysInMonth))->addDays($grace);
            $items[] = [
                'label' => 'Inscripción',
                'amount' => round(abs((float) $balance->inscription), 2),
                'date' => $due->toDateString(),
                'status' => $balance->inscription_status?->value,
                'overdue' => $due->isPast(),
            ];
        }

        foreach (self::MONTHS as $index => $month) {
            $status = $balance->{$month.'_status'}?->value ?? $balance->{$month.'_status'};
            $value = $balance->$month;

            if ($status === 'paid' || $value >= 0) {
                continue;
            }

            $monthBase = $base->copy()->addMonths($index);
            $due = $monthBase->copy()->day(min($day, $monthBase->daysInMonth))->addDays($grace);

            $items[] = [
                'label' => $this->monthLabel($month),
                'amount' => round(abs((float) $value), 2),
                'date' => $due->toDateString(),
                'status' => $status,
                'overdue' => $due->isPast(),
                'month' => $month,
            ];
        }

        return $items;
    }

    private function formatUpcomingEvaluations(Student $student, ?SchoolLapse $lapse): array
    {
        if (! $lapse) {
            return [];
        }

        return EvaluationPlanItem::whereHas('plan', function ($q) use ($student, $lapse) {
            $q->where('course_id', $student->course_id)
                ->where('status', 'approved')
                ->where('school_lapse_id', $lapse->id);
        })
            ->whereNotNull('scheduled_date')
            ->whereDate('scheduled_date', '>=', Carbon::now()->toDateString())
            ->with('plan.matter')
            ->orderBy('scheduled_date')
            ->limit(8)
            ->get()
            ->map(fn ($item) => [
                'title' => $item->name,
                'matter' => $item->plan?->matter?->name,
                'unit' => $item->unit_name,
                'type' => $item->assessment_type,
                'date' => $item->scheduled_date,
            ])
            ->values()
            ->all();
    }

    private function formatAnnouncements($students): array
    {
        $courseIds = $students->pluck('course_id')->filter()->unique()->values();
        $sectionIds = $students->pluck('section_id')->filter()->unique()->values();
        $studentIds = $students->pluck('id');

        return Announcement::published()
            ->where(function ($q) use ($courseIds, $sectionIds, $studentIds) {
                $q->where('audience', 'all')
                    ->orWhere(fn ($sub) => $sub->where('audience', 'course')->whereIn('course_id', $courseIds))
                    ->orWhere(fn ($sub) => $sub->where('audience', 'section')->whereIn('section_id', $sectionIds))
                    ->orWhere(fn ($sub) => $sub->where('audience', 'student')->whereIn('student_id', $studentIds));
            })
            ->orderByDesc('published_at')
            ->limit(10)
            ->get(['id', 'title', 'body', 'published_at'])
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'date' => $a->published_at?->toDateString(),
            ])
            ->values()
            ->all();
    }

    private function formatEvents($students): array
    {
        $courseIds = $students->pluck('course_id')->filter()->unique()->values();

        return SchoolEvent::published()
            ->whereDate('start_date', '>=', Carbon::now()->toDateString())
            ->where(function ($q) use ($courseIds) {
                $q->whereNull('course_id')->orWhereIn('course_id', $courseIds);
            })
            ->orderBy('start_date')
            ->limit(10)
            ->get(['id', 'title', 'description', 'type', 'start_date'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'description' => $e->description,
                'type' => $e->type,
                'date' => $e->start_date?->toDateString(),
            ])
            ->values()
            ->all();
    }

    private function momentLabel($lapse): string
    {
        $ordinals = [1 => '1er', 2 => '2do', 3 => '3er'];

        return ($ordinals[$lapse->number] ?? $lapse->number).' Momento';
    }

    private function monthLabel(string $month): string
    {
        $labels = [
            'september' => 'Septiembre', 'october' => 'Octubre', 'november' => 'Noviembre',
            'december' => 'Diciembre', 'january' => 'Enero', 'february' => 'Febrero',
            'march' => 'Marzo', 'april' => 'Abril', 'may' => 'Mayo', 'june' => 'Junio',
            'july' => 'Julio', 'august' => 'Agosto',
        ];

        return $labels[$month] ?? ucfirst($month);
    }
}
