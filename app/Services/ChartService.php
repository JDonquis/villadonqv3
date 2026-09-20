<?php

namespace App\Services;

use App\Models\BalancePayment;
use App\Models\BalanceStudent;
use App\Models\Course;
use App\Models\Payment;
use App\Models\SchoolLapse;
use App\Models\Student;
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
        if (!$schoolLapse instanceof SchoolLapse) {
            $lapse = SchoolLapse::find($schoolLapse);
        } else {
            $lapse = $schoolLapse;
        }

        if (!$lapse) {
            $lapse = SchoolLapse::where('status', 1)->first();
        }

        if (!$lapse) {
            return ['labels' => [], 'data' => []];
        }

        $courses = Course::orderBy('id')->get();
        $labels = [];
        $inscriptionData = [];
        $monthlyData = [];

        foreach ($courses as $course) {
            $labels[] = $course->name;

            $balanceSum = BalanceStudent::where('school_lapse_id', $lapse->id)
                ->whereHas('student', function ($q) use ($course) {
                    $q->where('course_id', $course->id)
                      ->where('status', '!=', 0);
                })
                ->selectRaw("
                    SUM(inscription) as sum_inscription,
                    SUM(september) as sum_september,
                    SUM(october) as sum_october,
                    SUM(november) as sum_november,
                    SUM(december) as sum_december,
                    SUM(january) as sum_january,
                    SUM(february) as sum_february,
                    SUM(march) as sum_march,
                    SUM(april) as sum_april,
                    SUM(may) as sum_may,
                    SUM(june) as sum_june,
                    SUM(july) as sum_july,
                    SUM(august) as sum_august
                ")
                ->first();

            $inscriptionDebt = abs((float) ($balanceSum->sum_inscription ?? 0));
            $monthlyDebt = 0;
            foreach (self::MONTHS as $month) {
                $monthlyDebt += abs((float) ($balanceSum->{"sum_$month"} ?? 0));
            }

            $inscriptionData[] = round($inscriptionDebt, 2);
            $monthlyData[] = round($monthlyDebt, 2);
        }

        return [
            'labels' => $labels,
            'inscription' => $inscriptionData,
            'monthly' => $monthlyData,
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

        $config = \App\Models\MainConfig::first();
        $monthlyPrice = (float) ($config->monthly_payment ?? 0);

        $labels = [];
        $rates = [];

        foreach ($lapses as $lapse) {
            $labels[] = $lapse->start . ' - ' . $lapse->end;

            // Expected = sum of each active student's effective monthly price from their balance
            $expected = BalanceStudent::whereHas('student', function ($q) {
                $q->where('status', '!=', 0);
            })
                ->where('school_lapse_id', $lapse->id)
                ->with('student')
                ->get()
                ->sum(function ($balance) use ($monthlyPrice) {
                    $student = $balance->student;
                    if (!$student) {
                        return 0;
                    }
                    $multiplier = $student->is_exempt
                        ? (1 - (($student->exemption_percentage ?? 0) / 100))
                        : 1;
                    return $monthlyPrice * $multiplier;
                });

            // Total collected in this lapse (all moments)
            $collected = Payment::where('status', '!=', 0)
                ->whereHas('balancePayments.balanceStudent', function ($q) use ($lapse) {
                    $q->where('school_lapse_id', $lapse->id);
                })
                ->sum('total_in_dolars');

            $rate = $expected > 0 ? round(($collected / $expected) * 100, 1) : 0.0;
            $rates[] = $rate;
        }

        return ['labels' => $labels, 'rates' => $rates];
    }

    public function topDebtors($limit = 10, $schoolLapse = null)
    {
        if (!$schoolLapse instanceof SchoolLapse) {
            $lapse = SchoolLapse::find($schoolLapse);
        } else {
            $lapse = $schoolLapse;
        }

        if (!$lapse) {
            $lapse = SchoolLapse::where('status', 1)->first();
        }

        if (!$lapse) {
            return [];
        }

        $balances = BalanceStudent::where('school_lapse_id', $lapse->id)
            ->whereHas('student', function ($q) {
                $q->where('status', '!=', 0);
            })
            ->with('student.course', 'student.section')
            ->get()
            ->map(function ($balance) {
                $debt = $balance->currentDebt();
                if ($debt >= 0) return null;

                return [
                    'name' => $balance->student->name . ' ' . $balance->student->last_name,
                    'ci' => $balance->student->ci,
                    'course' => $balance->student->course?->name ?? 'N/A',
                    'section' => $balance->student->section?->name ?? 'N/A',
                    'debt' => abs($debt),
                ];
            })
            ->filter()
            ->sortByDesc('debt')
            ->take($limit)
            ->values()
            ->toArray();

        return $balances;
    }

    public function annualVsMonthlyFlow($schoolLapse)
    {
        if (!$schoolLapse instanceof SchoolLapse) {
            $lapse = SchoolLapse::find($schoolLapse);
        } else {
            $lapse = $schoolLapse;
        }

        if (!$lapse) {
            $lapse = SchoolLapse::where('status', 1)->first();
        }

        if (!$lapse) {
            return [
                'pagado_mensual' => [],
                'esperado_mensual' => [],
                'real_acumulado' => [],
                'meta_acumulada' => []
            ];
        }

        $lapseId = $lapse->id;
        $lapseStart = Carbon::parse($lapse->start)->format('Y-m-d');

        // 1. Pagado real: Agrupado por la fecha en que se realizó el pago (incluye inscripciones y mensualidades)
        $paidByCalendarMonth = BalancePayment::whereHas('balanceStudent', function ($q) use ($lapseId) {
            $q->where('school_lapse_id', $lapseId);
        })
            ->join('payments', 'balance_payments.payment_id', '=', 'payments.id')
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

        // 2. Esperado mensual: Basado en el mes de la cuota + inscripciones en Septiembre
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

        // Sumar deudas restantes
        $rawSelect = "";
        foreach (self::MONTHS as $month) {
            $rawSelect .= "SUM($month) as sum_$month, ";
        }
        $rawSelect .= "SUM(inscription) as sum_inscription";

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

            // Agregar el esperado de inscripciones a Septiembre (índice 1)
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
        $isLapseActive = $lapse->status == 1;

        foreach (self::MONTHS as $index => $monthName) {
            $paid = $pagado_mensual_raw[$index];
            $expected = $esperado_mensual[$index];

            $targetDate = $startOfLapse->copy()->addMonths($index);
            $isFuture = $isLapseActive && $targetDate->gt($now->startOfMonth());

            $meta_sum += $expected;
            $meta_acumulada[] = $meta_sum;

            if ($isFuture && $paid == 0) {
                $pagado_mensual[] = "";
                $real_acumulado[] = "";
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
}
