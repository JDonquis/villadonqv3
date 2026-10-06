<?php

namespace App\Services;

use App\Enums\BalanceStudentStatusEnum;
use App\Models\BalancePayment;
use App\Models\BalanceStudent;
use App\Models\Course;
use App\Models\EvaluationPlanAttendanceSession;
use App\Models\MainConfig;
use App\Models\Payment;
use App\Models\SchoolLapse;
use App\Models\Student;
use App\Models\StudentAttendance;
use Carbon\Carbon;

class ChartService
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

    public function debtByCourse($schoolLapse)
    {
        $lapse = $this->resolveLapse($schoolLapse);

        if (! $lapse) {
            return ['labels' => [], 'inscription' => [], 'monthly' => []];
        }

        $courses = Course::orderBy('id')->get(['id', 'name']);

        $balances = BalanceStudent::where('school_lapse_id', $lapse->id)
            ->whereHas('student', fn ($q) => $q->where('status', '!=', 0))
            ->with('student:id,course_id')
            ->get();

        $byCourse = [];

        foreach ($balances as $balance) {
            $courseId = $balance->student?->course_id;
            if (! $courseId) {
                continue;
            }

            if (! isset($byCourse[$courseId])) {
                $byCourse[$courseId] = ['inscription' => 0.0, 'monthly' => 0.0];
            }

            if ($balance->inscription < 0) {
                $byCourse[$courseId]['inscription'] += abs((float) $balance->inscription);
            }

            foreach (self::MONTHS as $month) {
                $value = $balance->$month;

                if ($value < 0 && $this->isDueStatus($balance->{$month.'_status'})) {
                    $byCourse[$courseId]['monthly'] += abs((float) $value);
                }
            }
        }

        $labels = [];
        $inscriptionData = [];
        $monthlyData = [];

        foreach ($courses as $course) {
            $labels[] = $course->name;
            $inscriptionData[] = round($byCourse[$course->id]['inscription'] ?? 0, 2);
            $monthlyData[] = round($byCourse[$course->id]['monthly'] ?? 0, 2);
        }

        return [
            'labels' => $labels,
            'inscription' => $inscriptionData,
            'monthly' => $monthlyData,
        ];
    }

    /**
     * Morosidad por antigüedad (aging). Cada cuota vencida se agrupa según los
     * días transcurridos desde su fecha de vencimiento (día de pago + gracia).
     */
    public function aging($schoolLapse)
    {
        $lapse = $this->resolveLapse($schoolLapse);
        $config = MainConfig::first();
        $day = max(1, (int) ($config->day_of_monthly_payment ?? 1));
        $grace = max(0, (int) ($config->grace_period ?? 0));

        $labels = ['Al día', '1 - 30 días', '31 - 60 días', '+60 días'];
        $buckets = [0.0, 0.0, 0.0, 0.0];

        if (! $lapse) {
            return ['labels' => $labels, 'data' => $buckets, 'total' => 0.0];
        }

        $now = Carbon::now();
        $base = Carbon::parse($lapse->start)->startOfMonth();

        $balances = BalanceStudent::where('school_lapse_id', $lapse->id)
            ->whereHas('student', fn ($q) => $q->where('status', '!=', 0))
            ->get();

        $addToBucket = function (float $amount, Carbon $due) use (&$buckets, $now) {
            if (! $due->isPast()) {
                $buckets[0] += $amount;

                return;
            }

            $days = $due->diffInDays($now);

            if ($days <= 30) {
                $buckets[1] += $amount;
            } elseif ($days <= 60) {
                $buckets[2] += $amount;
            } else {
                $buckets[3] += $amount;
            }
        };

        $inscriptionDue = $base->copy()->day(min($day, $base->daysInMonth))->addDays($grace);

        foreach ($balances as $balance) {
            if ($balance->inscription < 0) {
                $addToBucket(abs((float) $balance->inscription), $inscriptionDue->copy());
            }

            foreach (self::MONTHS as $index => $month) {
                $value = $balance->$month;

                if ($value >= 0 || ! $this->isDueStatus($balance->{$month.'_status'})) {
                    continue;
                }

                $monthBase = $base->copy()->addMonths($index);
                $due = $monthBase->copy()
                    ->day(min($day, $monthBase->daysInMonth))
                    ->addDays($grace);

                $addToBucket(abs((float) $value), $due);
            }
        }

        $buckets = array_map(fn ($v) => round($v, 2), $buckets);

        return [
            'labels' => $labels,
            'data' => $buckets,
            'total' => round(array_sum($buckets), 2),
        ];
    }

    /**
     * Distribución del cobro por método (canal) y moneda.
     */
    public function collectionByChannel($schoolLapse)
    {
        $lapse = $this->resolveLapse($schoolLapse);

        if (! $lapse) {
            return ['labels' => [], 'usd' => [], 'bs' => []];
        }

        $rows = Payment::query()
            ->join('account_payments', 'payments.account_payment_id', '=', 'account_payments.id')
            ->join('payment_methods', 'account_payments.payment_method_id', '=', 'payment_methods.id')
            ->where('payments.status', '!=', 0)
            ->whereBetween('payments.date', [$lapse->start, $lapse->end])
            ->selectRaw('payment_methods.name as canal, account_payments.cash_currency as moneda, SUM(payments.total_in_dolars) as usd, SUM(payments.total_in_bs) as bs, COUNT(*) as cantidad')
            ->groupBy('payment_methods.name', 'account_payments.cash_currency')
            ->get();

        $labels = [];
        $usd = [];
        $bs = [];

        foreach ($rows as $row) {
            $label = $row->canal;

            if ($row->moneda) {
                $label .= ' ('.$row->moneda.')';
            }

            $labels[] = $label;
            $usd[] = round((float) $row->usd, 2);
            $bs[] = round((float) $row->bs, 2);
        }

        return ['labels' => $labels, 'usd' => $usd, 'bs' => $bs];
    }

    /**
     * Resumen de matrícula (activos/retirados/graduados) e índice de asistencia
     * del día a partir de las sesiones registradas por los profesores.
     */
    public function attendanceSummary($schoolLapse = null)
    {
        $active = Student::where('status', '!=', 0)->count();
        $withdrawn = Student::where('status', 0)
            ->where(fn ($q) => $q->where('graduate', 0)->orWhereNull('graduate'))
            ->count();
        $graduated = Student::where('graduate', 1)->where('status', 0)->count();

        $today = Carbon::now()->toDateString();
        $lapse = $this->resolveLapse($schoolLapse);

        $sessionQuery = EvaluationPlanAttendanceSession::where('date', $today);

        if ($lapse) {
            $sessionQuery->whereHas('plan', fn ($q) => $q->where('school_lapse_id', $lapse->id));
        }

        $sessionIds = $sessionQuery->pluck('id');

        $present = 0;
        $excused = 0;
        $absent = 0;

        if ($sessionIds->isNotEmpty()) {
            $counts = StudentAttendance::whereIn('session_id', $sessionIds)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $present = (int) ($counts['present'] ?? 0);
            $excused = (int) ($counts['excused'] ?? 0);
            $absent = (int) ($counts['absent'] ?? 0);
        }

        $total = $present + $excused + $absent;

        return [
            'active' => $active,
            'withdrawn' => $withdrawn,
            'graduated' => $graduated,
            'attendance' => [
                'date' => $today,
                'sessions' => $sessionIds->count(),
                'present' => $present,
                'excused' => $excused,
                'absent' => $absent,
                'total' => $total,
                'rate' => $total > 0 ? round(($present / $total) * 100, 1) : null,
            ],
        ];
    }

    public function collectionRateTrend($years = 5)
    {
        $lapses = SchoolLapse::orderBy('start', 'desc')
            ->limit($years)
            ->get()
            ->reverse();

        if ($lapses->isEmpty()) {
            return ['labels' => [], 'rates' => []];
        }

        $config = MainConfig::first();
        $monthlyPrice = (float) ($config->monthly_payment ?? 0);

        $labels = [];
        $rates = [];

        foreach ($lapses as $lapse) {
            $labels[] = $lapse->start.' - '.$lapse->end;

            // Esperado anual: una mensualidad por alumno (con exención) por cada
            // mes del período, para que la tasa sea comparable contra lo cobrado.
            $multipliers = $this->studentMultipliers($lapse->id);
            $expected = $monthlyPrice * 12 * array_sum($multipliers);

            $collected = (float) BalancePayment::whereHas('balanceStudent', function ($q) use ($lapse) {
                $q->where('school_lapse_id', $lapse->id);
            })
                ->whereHas('payment', fn ($q) => $q->where('status', '!=', 0))
                ->sum('amount');

            $rate = $expected > 0 ? round(($collected / $expected) * 100, 1) : 0.0;
            $rates[] = $rate;
        }

        return ['labels' => $labels, 'rates' => $rates];
    }

    public function topDebtors($limit = 10, $schoolLapse = null)
    {
        $lapse = $this->resolveLapse($schoolLapse);

        if (! $lapse) {
            return [];
        }

        return BalanceStudent::where('school_lapse_id', $lapse->id)
            ->whereHas('student', fn ($q) => $q->where('status', '!=', 0))
            ->with('student.course', 'student.section')
            ->get()
            ->map(function ($balance) {
                $debt = $balance->currentDebt();

                if ($debt <= 0) {
                    return null;
                }

                return [
                    'name' => trim($balance->student->name.' '.$balance->student->last_name),
                    'ci' => $balance->student->ci,
                    'course' => $balance->student->course?->name ?? 'N/A',
                    'section' => $balance->student->section?->name ?? 'N/A',
                    'debt' => $debt,
                ];
            })
            ->filter()
            ->sortByDesc('debt')
            ->take($limit)
            ->values()
            ->toArray();
    }

    public function annualVsMonthlyFlow($schoolLapse)
    {
        $lapse = $this->resolveLapse($schoolLapse);

        if (! $lapse) {
            return [
                'pagado_mensual' => [],
                'esperado_mensual' => [],
                'real_acumulado' => [],
                'meta_acumulada' => [],
            ];
        }

        $lapseId = $lapse->id;
        $lapseStart = Carbon::parse($lapse->start)->format('Y-m-d');

        // 1. Pagado real agrupado por el mes calendario del pago.
        $paidByCalendarMonth = BalancePayment::whereHas('balanceStudent', function ($q) use ($lapseId) {
            $q->where('school_lapse_id', $lapseId);
        })
            ->join('payments', 'balance_payments.payment_id', '=', 'payments.id')
            ->where('payments.status', '!=', 0)
            ->selectRaw("
                CASE
                    WHEN TIMESTAMPDIFF(MONTH, '$lapseStart', payments.date) < 0 THEN 0
                    WHEN TIMESTAMPDIFF(MONTH, '$lapseStart', payments.date) > 11 THEN 11
                    ELSE TIMESTAMPDIFF(MONTH, '$lapseStart', payments.date)
                END as month_index,
                SUM(balance_payments.amount) as total
            ")
            ->groupBy('month_index')
            ->pluck('total', 'month_index');

        // 2. Esperado mensual: cuota por mes + inscripciones en septiembre.
        $paidForQuotaMonth = BalancePayment::whereHas('balanceStudent', function ($q) use ($lapseId) {
            $q->where('school_lapse_id', $lapseId);
        })
            ->whereNotNull('month')
            ->selectRaw('month, SUM(amount) as total')
            ->groupBy('month')
            ->pluck('total', 'month');

        $paidForInscriptions = (float) BalancePayment::whereHas('balanceStudent', function ($q) use ($lapseId) {
            $q->where('school_lapse_id', $lapseId);
        })
            ->where('is_inscription', true)
            ->sum('amount');

        $rawSelect = '';
        foreach (self::MONTHS as $month) {
            $rawSelect .= "SUM($month) as sum_$month, ";
        }
        $rawSelect .= 'SUM(inscription) as sum_inscription';

        $balancesSum = BalanceStudent::where('school_lapse_id', $lapseId)
            ->selectRaw($rawSelect)
            ->first();

        $totalExpectedInscriptions = $paidForInscriptions + abs((float) ($balancesSum->sum_inscription ?? 0));

        $pagado_mensual_raw = array_fill(0, 12, 0.0);
        foreach ($paidByCalendarMonth as $index => $total) {
            $pagado_mensual_raw[$index] = (float) $total;
        }

        $esperado_mensual = [];
        foreach (self::MONTHS as $monthName) {
            $paid = (float) ($paidForQuotaMonth[$monthName] ?? 0);
            $remaining = abs((float) ($balancesSum->{"sum_$monthName"} ?? 0));
            $expected = $paid + $remaining;

            if ($monthName === 'september') {
                $expected += $totalExpectedInscriptions;
            }

            $esperado_mensual[] = $expected;
        }

        $pagado_mensual = [];
        $real_acumulado = [];
        $meta_acumulada = [];

        $real_sum = 0;
        $meta_sum = 0;

        $now = Carbon::now();
        $startOfLapse = Carbon::parse($lapse->start)->startOfMonth();
        $isLapseActive = (int) $lapse->status === 1;

        foreach (self::MONTHS as $index => $monthName) {
            $paid = $pagado_mensual_raw[$index];
            $expected = $esperado_mensual[$index];

            $targetDate = $startOfLapse->copy()->addMonths($index);
            $isFuture = $isLapseActive && $targetDate->gt($now->copy()->startOfMonth());

            $meta_sum += $expected;
            $meta_acumulada[] = $meta_sum;

            if ($isFuture && $paid == 0) {
                $pagado_mensual[] = '';
                $real_acumulado[] = '';
            } else {
                $pagado_mensual[] = $paid;
                $real_sum += $paid;
                $real_acumulado[] = $real_sum;
            }
        }

        return [
            'pagado_mensual' => $pagado_mensual,
            'esperado_mensual' => $esperado_mensual,
            'real_acumulado' => $real_acumulado,
            'meta_acumulada' => $meta_acumulada,
        ];
    }

    private function resolveLapse($schoolLapse): ?SchoolLapse
    {
        if ($schoolLapse instanceof SchoolLapse) {
            return $schoolLapse;
        }

        if ($schoolLapse) {
            $found = SchoolLapse::find($schoolLapse);
            if ($found) {
                return $found;
            }
        }

        return SchoolLapse::where('status', 1)->first()
            ?? SchoolLapse::orderByDesc('start')->first();
    }

    private function isDueStatus($status): bool
    {
        $value = $status instanceof BalanceStudentStatusEnum ? $status->value : $status;

        return in_array($value, [
            BalanceStudentStatusEnum::Debt->value,
            BalanceStudentStatusEnum::PartiallyPaid->value,
        ], true);
    }

    /**
     * Multiplicador efectivo (por exención) de cada alumno con balance del período.
     */
    private function studentMultipliers(int $lapseId): array
    {
        $studentIds = BalanceStudent::where('school_lapse_id', $lapseId)
            ->whereHas('student', fn ($q) => $q->where('status', '!=', 0))
            ->distinct('student_id')
            ->pluck('student_id');

        return Student::whereIn('id', $studentIds)
            ->get(['id', 'is_exempt', 'exemption_percentage'])
            ->mapWithKeys(function (Student $student) {
                $multiplier = $student->is_exempt
                    ? (1 - (($student->exemption_percentage ?? 0) / 100))
                    : 1;

                return [$student->id => max(0, $multiplier)];
            })
            ->all();
    }
}
