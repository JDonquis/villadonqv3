<?php

namespace App\Services;

use App\Models\BalancePayment;
use App\Models\BalanceStudent;
use App\Models\MainConfig;
use App\Models\Payment;
use App\Models\Representative;
use App\Models\SchoolLapse;
use App\Models\Student;
use Carbon\Carbon;

class DashboardService
{
    private const MONTH_ORDER = [
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

    public function getKpiData(): array
    {
        $currentLapse = SchoolLapse::where('status', 1)->first();
        $config = MainConfig::first();
        $monthlyPrice = (float) ($config->monthly_payment ?? 0);

        $totalStudents = Student::where('status', '!=', 0)->count();
        $totalRepresentatives = Student::where('status', '!=', 0)
            ->whereNotNull('representative_id')
            ->join('representatives', 'students.representative_id', '=', 'representatives.id')
            ->distinct('representatives.user_id')
            ->count('representatives.user_id');
        $totalOutstandingDebt = $this->calculateTotalOutstandingDebt();
        $studentsAtRisk = $this->calculateStudentsAtRisk();
        $collectionRate = $this->calculateCollectionRate($currentLapse, $monthlyPrice);
        $thisMonthIncome = $this->calculateThisMonthIncome($currentLapse);
        $pendingPayments = Payment::where('status', 'pending')->count();

        return [
            'total_students' => $totalStudents,
            'total_representatives' => $totalRepresentatives,
            'total_outstanding_debt' => (float) $totalOutstandingDebt,
            'students_at_risk' => $studentsAtRisk,
            'collection_rate' => $collectionRate,
            'this_month_income' => (float) $thisMonthIncome,
            'pending_payments' => $pendingPayments,
        ];
    }

    private function calculateTotalOutstandingDebt(): float
    {
        return (float) BalanceStudent::get()->sum(fn ($balance) => $balance->currentDebt());
    }

    private function calculateStudentsAtRisk(): int
    {
        return BalanceStudent::whereHas('student', function ($q) {
            $q->where('status', '!=', 0);
        })->get()->filter(function ($balance) {
            return $this->isStudentAtRisk($balance);
        })->count();
    }

    private function isStudentAtRisk($balance): bool
    {
        // Inscription debt
        if ($balance->inscription < 0) {
            return true;
        }

        // 2+ months debt
        $debtMonths = 0;
        foreach (self::MONTH_ORDER as $month) {
            $monthValue = $balance->$month;
            $monthStatus = $balance->{$month . '_status'};
            if ($monthValue < 0 && in_array($monthStatus, ['debt', 'partially_paid'], true)) {
                $debtMonths++;
            }
        }

        return $debtMonths >= 2;
    }

    private function calculateCollectionRate(?SchoolLapse $currentLapse, float $monthlyPrice): float
    {
        if (!$currentLapse || $monthlyPrice <= 0) {
            return 0.0;
        }

        // Expected = sum of each active student's effective monthly price from their balance
        $expected = BalanceStudent::whereHas('student', function ($q) {
            $q->where('status', '!=', 0);
        })
            ->where('school_lapse_id', $currentLapse->id)
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

        // Current school month (moment)
        $currentMoment = $currentLapse->lapses->first(function ($m) {
            $now = Carbon::now();
            return $now->between($m->start, $m->end);
        }) ?? $currentLapse->lapses->last();

        if (!$currentMoment) {
            return 0.0;
        }

        // Collected in current school month
        $collected = Payment::where('status', '!=', 0)
            ->whereBetween('date', [$currentMoment->start, $currentMoment->end])
            ->sum('total_in_dolars');

        return $expected > 0 ? round(($collected / $expected) * 100, 1) : 0.0;
    }

    private function calculateThisMonthIncome(?SchoolLapse $currentLapse): float
    {
        if (!$currentLapse) {
            return 0.0;
        }

        $currentMoment = $currentLapse->lapses->first(function ($m) {
            $now = Carbon::now();
            return $now->between($m->start, $m->end);
        }) ?? $currentLapse->lapses->last();

        if (!$currentMoment) {
            return 0.0;
        }

        return (float) Payment::where('status', '!=', 0)
            ->whereBetween('date', [$currentMoment->start, $currentMoment->end])
            ->sum('total_in_dolars');
    }
}