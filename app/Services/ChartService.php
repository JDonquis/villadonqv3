<?php

namespace App\Services;

use App\Models\BalancePayment;
use App\Models\BalanceStudent;
use App\Models\SchoolLapse;
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
