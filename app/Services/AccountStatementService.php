<?php

namespace App\Services;

use App\Enums\BalanceStudentStatusEnum;
use App\Models\BalanceStudent;
use App\Models\MainConfig;
use App\Models\SchoolLapse;
use App\Models\Section;
use Illuminate\Support\Facades\DB;

class AccountStatementService
{
    private const MONTHS = [
        'january',
        'february',
        'march',
        'april',
        'may',
        'june',
        'july',
        'august',
        'september',
        'october',
        'november',
        'december',
    ];

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

    public function getAll($params = [])
    {
        $currentLapse = SchoolLapse::where('status', 1)->first();
        $config = MainConfig::first();
        $dayOfPayment = $config->day_of_monthly_payment ?? 5;
        $gracePeriod = $config->grace_period ?? 0;

        $currentMonthName = strtolower(now()->format('F'));
        $currentMonthIndex = array_search($currentMonthName, self::SCHOOL_MONTHS);

        // SQL expression for debt sum (absolute value of negative numbers)
        $debtSumSql = $this->getDebtSumSql();
        // SQL expression for "has_real_debt" logic
        $hasRealDebtSql = $this->getHasRealDebtSql($currentLapse, $currentMonthIndex, $dayOfPayment, $gracePeriod);

        // Base balance query for totals
        $balanceTotalsQuery = BalanceStudent::query()
            ->join('students', 'balance_students.student_id', '=', 'students.id')
            ->join('courses', 'students.course_id', '=', 'courses.id')
            ->join('sections', 'students.section_id', '=', 'sections.id')
            ->join('representatives', 'students.representative_id', '=', 'representatives.id')
            ->join('users', 'representatives.user_id', '=', 'users.id')
            ->where(function ($q) use ($hasRealDebtSql) {
                $q->where('students.status', '!=', 0)
                    ->orWhere(function ($q) use ($hasRealDebtSql) {
                        $q->where('students.graduate', 1)
                            ->whereRaw($hasRealDebtSql);
                    });
            });

        $this->applyFilters($balanceTotalsQuery, $params, $hasRealDebtSql, $currentLapse);

        // Totals for filtered query
        $allBalancesForTotals = (clone $balanceTotalsQuery)
            ->select('balance_students.*')
            ->with('balancePayments')
            ->get();
        $totalDebt = $allBalancesForTotals->sum(fn($b) => $this->calculateBalanceDebt($b));
        $totalIncome = $allBalancesForTotals->sum(fn($b) => $b->balancePayments->sum('amount'));

        // Student query for pagination
        $studentQuery = \App\Models\Student::query()
            ->join('courses', 'students.course_id', '=', 'courses.id')
            ->join('sections', 'students.section_id', '=', 'sections.id')
            ->join('representatives', 'students.representative_id', '=', 'representatives.id')
            ->join('users', 'representatives.user_id', '=', 'users.id')
            ->where(function ($q) use ($hasRealDebtSql, $params, $currentLapse) {
                if (!empty($params['debt_filter']) && $params['debt_filter'] === 'graduated_with_debts') {
                    $q->where('students.graduate', 1);
                } else {
                    $q->where('students.status', '!=', 0)
                        ->orWhere(function ($q) use ($hasRealDebtSql) {
                            $q->where('students.graduate', 1)
                                ->whereHas('balances', function($q) use ($hasRealDebtSql) {
                                    $q->whereRaw($hasRealDebtSql);
                                });
                        });
                }
            })
            ->select('students.*');

        // Apply search filter (only needs the already joined tables)
        if (!empty($params['search'])) {
            $search = $params['search'];
            $studentQuery->where(function ($q) use ($search) {
                $q->where('students.name', 'LIKE', "%$search%")
                    ->orWhere('students.last_name', 'LIKE', "%$search%")
                    ->orWhere('students.ci', 'LIKE', "%$search%")
                    ->orWhere('users.name', 'LIKE', "%$search%")
                    ->orWhere('users.last_name', 'LIKE', "%$search%")
                    ->orWhere('users.ci', 'LIKE', "%$search%")
                    ->orWhereRaw("CONCAT(students.name, ' ', students.last_name) LIKE ?", ["%$search%"])
                    ->orWhereRaw("CONCAT(users.name, ' ', users.last_name) LIKE ?", ["%$search%"]);
            });
        }

        // Apply debt filter via whereHas
        if (!empty($params['debt_filter'])) {
            $studentQuery->whereHas('balances', function($q) use ($params, $hasRealDebtSql, $currentLapse) {
                $this->applyDebtFilter($q, $params['debt_filter'], $hasRealDebtSql, $currentLapse);
            });
        } else {
            // Ensure student has at least one balance to show up in account statement
            $studentQuery->has('balances');
        }

        // Sorting
        $sortField = $params['sort_field'] ?? 'debt';
        $sortDirection = $params['sort_direction'] ?? 'desc';

        switch ($sortField) {
            case 'debt':
                $studentQuery->orderBy(
                    BalanceStudent::selectRaw("SUM($debtSumSql)")
                        ->whereColumn('student_id', 'students.id')
                        ->where(function($q) use ($params, $hasRealDebtSql, $currentLapse) {
                            if (!empty($params['debt_filter'])) {
                                $this->applyDebtFilter($q, $params['debt_filter'], $hasRealDebtSql, $currentLapse);
                            }
                        })
                    , $sortDirection
                );
                break;
            case 'name':
                $studentQuery->orderBy('students.name', $sortDirection);
                break;
            case 'last_name':
                $studentQuery->orderBy('students.last_name', $sortDirection);
                break;
            case 'course':
                $studentQuery->orderBy('courses.name', $sortDirection);
                break;
            case 'section':
                $studentQuery->orderBy('sections.name', $sortDirection);
                break;
            default:
                $studentQuery->orderBy('students.id', 'desc');
        }

        // Pagination
        $perPage = $params['per_page'] ?? 25;
        $paginatedStudents = $studentQuery->with([
            'course',
            'section',
            'representative.user',
            'balances' => function($q) use ($params, $hasRealDebtSql, $currentLapse) {
                if (!empty($params['debt_filter'])) {
                    $this->applyDebtFilter($q, $params['debt_filter'], $hasRealDebtSql, $currentLapse);
                }
            },
            'balances.schoolLapse',
            'balances.balancePayments.payment.accountPayment.method'
        ])->paginate($perPage);

        // Transformation to match frontend expectation
        $mappedItems = $paginatedStudents->getCollection()->map(function ($student) use ($currentLapse, $currentMonthIndex, $dayOfPayment, $gracePeriod) {
            $transformedBalances = $student->balances->map(function ($balance) use ($currentLapse, $currentMonthIndex, $dayOfPayment, $gracePeriod) {
                $balanceDebt = $this->calculateBalanceDebt($balance);
                $hasRealDebt = $this->checkHasRealDebt($balance, $currentLapse, $currentMonthIndex, $dayOfPayment, $gracePeriod);
                $balanceIncome = $balance->balancePayments->sum('amount');

                return [
                    'id' => $balance->id,
                    'status' => $balance->status,
                    'school_lapse' => $balance->schoolLapse,
                    'inscription' => $balance->inscription,
                    'inscription_status' => $balance->inscription_status,
                    'months' => collect(self::MONTHS)->mapWithKeys(function ($month) use ($balance) {
                        return [$month => $balance->$month, $month . '_status' => $balance->{$month . '_status'}];
                    }),
                    'total_debt' => $balanceDebt,
                    'total_income' => $balanceIncome,
                    'has_real_debt' => $hasRealDebt,
                    'balance_payments' => $balance->balancePayments
                        ->groupBy(fn($bp) => $bp->is_inscription ? 'inscription' : $bp->month)
                        ->map(fn($bps) => $bps->map(fn($bp) => [
                            'id' => $bp->id,
                            'amount' => $bp->amount,
                            'payment' => $bp->payment ? [
                                'id' => $bp->payment->id,
                                'date' => $bp->payment->date,
                                'total_in_dolars' => $bp->payment->total_in_dolars,
                                'total_in_bs' => $bp->payment->total_in_bs,
                                'reference' => $bp->payment->reference,
                                'observations' => $bp->payment->observations,
                                'account_payment' => $bp->payment->accountPayment ? [
                                    'id' => $bp->payment->accountPayment->id,
                                    'person_name' => $bp->payment->accountPayment->person_name,
                                    'method' => $bp->payment->accountPayment->method,
                                ] : null,
                            ] : null,
                        ])),
                ];
            });

            return [
                'id' => $student->id,
                'balance_id' => $transformedBalances->first()['id'] ?? null,
                'name' => $student->name,
                'last_name' => $student->last_name,
                'ci' => $student->ci,
                'phone_number' => $student->phone_number,
                'gradute' => $student->graduate,
                'is_exempt' => $student->is_exempt,
                'exemption_percentage' => $student->exemption_percentage,
                'exemption_observations' => $student->exemption_observations,
                'course' => $student->course,
                'section' => $student->section,
                'representative' => $student->representative,
                'balances' => $transformedBalances->values()->all(),
                'total_debt' => (float) $transformedBalances->sum('total_debt'),
                'total_income' => (float) $transformedBalances->sum('total_income'),
            ];
        });

        $paginatedStudents->setCollection($mappedItems);

        return [
            'students' => $paginatedStudents,
            'total_debt' => $totalDebt,
            'total_income' => $totalIncome,
            'school_lapses' => SchoolLapse::where('status', '!=', 0)->get(),
            'sections' => Section::all(),
        ];
    }

    private function applyFilters($query, $params, $hasRealDebtSql, $currentLapse)
    {
        // Search Filter
        if (!empty($params['search'])) {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('students.name', 'LIKE', "%$search%")
                    ->orWhere('students.last_name', 'LIKE', "%$search%")
                    ->orWhere('students.ci', 'LIKE', "%$search%")
                    ->orWhere('users.name', 'LIKE', "%$search%")
                    ->orWhere('users.last_name', 'LIKE', "%$search%")
                    ->orWhere('users.ci', 'LIKE', "%$search%")
                    ->orWhereRaw("CONCAT(students.name, ' ', students.last_name) LIKE ?", ["%$search%"])
                    ->orWhereRaw("CONCAT(users.name, ' ', users.last_name) LIKE ?", ["%$search%"]);
            });
        }

        // Debt Filter
        if (!empty($params['debt_filter'])) {
            if ($params['debt_filter'] === 'graduated_with_debts') {
                $query->where('students.graduate', 1);
            }
            $this->applyDebtFilter($query, $params['debt_filter'], $hasRealDebtSql, $currentLapse);
        }
    }

    private function applyDebtFilter($query, $debtFilter, $hasRealDebtSql, $currentLapse)
    {
        switch ($debtFilter) {
            case 'graduated_with_debts':
            case 'debtors':
                $query->whereRaw($hasRealDebtSql);
                break;

            case 'current_period':
                if ($currentLapse) {
                    $query->where('balance_students.school_lapse_id', $currentLapse->id)
                        ->whereRaw($hasRealDebtSql);
                } else {
                    $query->whereRaw('1=0');
                }
                break;

            case 'previous_period':
                $previousLapse = null;
                if ($currentLapse) {
                    $previousLapse = SchoolLapse::where('start', '<', $currentLapse->start)
                        ->orderBy('start', 'desc')
                        ->first();
                }
                if ($previousLapse) {
                    $query->where('balance_students.school_lapse_id', $previousLapse->id)
                        ->whereRaw($hasRealDebtSql);
                } else {
                    $query->whereRaw('1=0');
                }
                break;

            case 'exempted':
                $query->where('students.is_exempt', 1);
                break;

            case 'up_to_date':
                $query->whereRaw("NOT ($hasRealDebtSql)");
                break;
        }
    }

    private function getDebtSumSql(): string
    {
        $cols = array_merge(['inscription'], self::MONTHS);
        $parts = array_map(fn($col) => "(CASE WHEN $col < 0 THEN ABS($col) ELSE 0 END)", $cols);
        return implode(' + ', $parts);
    }

    private function getHasRealDebtSql($currentLapse, $currentMonthIndex, $dayOfPayment, $gracePeriod): string
    {
        $debtVal = BalanceStudentStatusEnum::Debt->value;
        $partialVal = BalanceStudentStatusEnum::PartiallyPaid->value;
        $isPastDay = now()->day >= ($dayOfPayment + $gracePeriod);

        $sql = "(inscription_status = '$debtVal' OR inscription_status = '$partialVal')";

        foreach (self::SCHOOL_MONTHS as $index => $month) {
            $col = $month . '_status';

            $isDueCondition = "";
            if ($currentLapse) {
                $lapseId = $currentLapse->id;
                $pastLapsesSubquery = "(SELECT id FROM school_lapses WHERE start < '{$currentLapse->start}')";

                $isDueCondition = "(school_lapse_id IN $pastLapsesSubquery OR (school_lapse_id = $lapseId AND (";
                if ($index < $currentMonthIndex) {
                    $isDueCondition .= "1=1";
                } elseif ($index == $currentMonthIndex) {
                    $isDueCondition .= ($isPastDay ? "1=1" : "1=0");
                } else {
                    $isDueCondition .= "1=0";
                }
                $isDueCondition .= ")))";
            } else {
                $isDueCondition = "1=1";
            }

            $sql .= " OR (($col = '$debtVal' OR $col = '$partialVal') AND $isDueCondition)";
        }

        return "($sql)";
    }

    private function calculateBalanceDebt($balance): float
    {
        $debt = 0;
        if ($balance->inscription < 0) $debt += abs($balance->inscription);
        foreach (self::MONTHS as $month) {
            if ($balance->$month < 0) $debt += abs($balance->$month);
        }
        return (float) $debt;
    }

    private function checkHasRealDebt($balance, $currentLapse, $currentMonthIndex, $dayOfPayment, $gracePeriod): bool
    {
        if (
            $balance->inscription_status === BalanceStudentStatusEnum::Debt ||
            $balance->inscription_status === BalanceStudentStatusEnum::PartiallyPaid
        ) {
            return true;
        }

        $isPastDay = now()->day >= ($dayOfPayment + $gracePeriod);

        foreach (self::SCHOOL_MONTHS as $index => $month) {
            $status = $balance->{$month . '_status'};
            if ($status === BalanceStudentStatusEnum::Debt || $status === BalanceStudentStatusEnum::PartiallyPaid) {
                if ($currentLapse) {
                    if ($balance->school_lapse_id === $currentLapse->id) {
                        if ($index < $currentMonthIndex || ($index == $currentMonthIndex && $isPastDay)) {
                            return true;
                        }
                    } elseif ($balance->schoolLapse && $balance->schoolLapse->start < $currentLapse->start) {
                        return true;
                    }
                } else {
                    return true;
                }
            }
        }
        return false;
    }
}
