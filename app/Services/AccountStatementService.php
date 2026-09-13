<?php

namespace App\Services;

use App\Enums\BalanceStudentStatusEnum;
use App\Models\BalanceStudent;
use App\Models\SchoolLapse;
use App\Models\Section;
use App\Models\Student;

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

    public function getAll($params = [])
    {
        $currentLapse = SchoolLapse::where('status', 1)->first();

        // Misma definición de deuda que el modelo: inscripción negativa + meses con status debt/partially_paid.
        $debtSumSql = $this->getDebtSumSql();
        $hasDebtSql = "($debtSumSql) > 0";

        // Base balance query for totals
        $balanceTotalsQuery = BalanceStudent::query()
            ->join('students', 'balance_students.student_id', '=', 'students.id')
            ->join('courses', 'students.course_id', '=', 'courses.id')
            ->join('sections', 'students.section_id', '=', 'sections.id')
            ->join('representatives', 'students.representative_id', '=', 'representatives.id')
            ->join('users', 'representatives.user_id', '=', 'users.id')
            ->where(function ($q) use ($hasDebtSql) {
                $q->where('students.status', '!=', 0)
                    ->orWhere(function ($q) use ($hasDebtSql) {
                        $q->where('students.graduate', 1)
                            ->whereRaw($hasDebtSql);
                    });
            });

        $this->applyFilters($balanceTotalsQuery, $params, $hasDebtSql, $currentLapse);

        // Totals for filtered query
        $allBalancesForTotals = (clone $balanceTotalsQuery)
            ->select('balance_students.*')
            ->with('balancePayments')
            ->get();
        $totalDebt = $allBalancesForTotals->sum(fn ($b) => $b->currentDebt());
        $totalIncome = $allBalancesForTotals->sum(fn ($b) => $b->balancePayments->sum('amount'));

        // Student query for pagination
        $studentQuery = Student::query()
            ->join('courses', 'students.course_id', '=', 'courses.id')
            ->join('sections', 'students.section_id', '=', 'sections.id')
            ->join('representatives', 'students.representative_id', '=', 'representatives.id')
            ->join('users', 'representatives.user_id', '=', 'users.id')
            ->where(function ($q) use ($hasDebtSql, $params) {
                if (! empty($params['debt_filter']) && $params['debt_filter'] === 'graduated_with_debts') {
                    $q->where('students.graduate', 1);
                } else {
                    $q->where('students.status', '!=', 0)
                        ->orWhere(function ($q) use ($hasDebtSql) {
                            $q->where('students.graduate', 1)
                                ->whereHas('balances', function ($q) use ($hasDebtSql) {
                                    $q->whereRaw($hasDebtSql);
                                });
                        });
                }
            })
            ->select('students.*');

        // Apply search filter (only needs the already joined tables)
        if (! empty($params['search'])) {
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
        if (! empty($params['debt_filter'])) {
            if ($params['debt_filter'] === 'exempted') {
                $studentQuery->where('students.is_exempt', 1);
            } else {
                $studentQuery->whereHas('balances', function ($q) use ($params, $hasDebtSql, $currentLapse) {
                    $this->applyDebtFilter($q, $params['debt_filter'], $hasDebtSql, $currentLapse);
                });
            }
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
                        ->where(function ($q) use ($params, $hasDebtSql, $currentLapse) {
                            if (! empty($params['debt_filter'])) {
                                $this->applyDebtFilter($q, $params['debt_filter'], $hasDebtSql, $currentLapse);
                            }
                        }), $sortDirection
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
            'balances' => function ($q) use ($params, $hasDebtSql, $currentLapse) {
                if (! empty($params['debt_filter'])) {
                    $this->applyDebtFilter($q, $params['debt_filter'], $hasDebtSql, $currentLapse);
                }
            },
            'balances.schoolLapse',
            'balances.balancePayments.payment.accountPayment.method',
        ])->paginate($perPage)->withQueryString();

        // Transformation to match frontend expectation
        $mappedItems = $paginatedStudents->getCollection()->map(function ($student) {
            $transformedBalances = $student->balances->map(function ($balance) {
                $balanceDebt = $balance->currentDebt();
                $hasRealDebt = $balanceDebt > 0;
                $balanceIncome = $balance->balancePayments->sum('amount');

                return [
                    'id' => $balance->id,
                    'status' => $balance->status,
                    'school_lapse' => $balance->schoolLapse,
                    'inscription' => $balance->inscription,
                    'inscription_status' => $balance->inscription_status,
                    'months' => collect(self::MONTHS)->mapWithKeys(function ($month) use ($balance) {
                        return [$month => $balance->$month, $month.'_status' => $balance->{$month.'_status'}];
                    }),
                    'total_debt' => $balanceDebt,
                    'total_income' => $balanceIncome,
                    'has_real_debt' => $hasRealDebt,
                    'balance_payments' => $balance->balancePayments
                        ->groupBy(fn ($bp) => $bp->is_inscription ? 'inscription' : $bp->month)
                        ->map(fn ($bps) => $bps->map(fn ($bp) => [
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

    private function applyFilters($query, $params, $hasDebtSql, $currentLapse)
    {
        // Search Filter
        if (! empty($params['search'])) {
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
        if (! empty($params['debt_filter'])) {
            if ($params['debt_filter'] === 'graduated_with_debts') {
                $query->where('students.graduate', 1);
            }
            if ($params['debt_filter'] === 'exempted') {
                $query->where('students.is_exempt', 1);
            }
            $this->applyDebtFilter($query, $params['debt_filter'], $hasDebtSql, $currentLapse);
        }
    }

    private function applyDebtFilter($query, $debtFilter, $hasDebtSql, $currentLapse)
    {
        switch ($debtFilter) {
            case 'graduated_with_debts':
            case 'debtors':
                $query->whereRaw($hasDebtSql);
                break;

            case 'current_period':
                if ($currentLapse) {
                    $query->where('balance_students.school_lapse_id', $currentLapse->id)
                        ->whereRaw($hasDebtSql);
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
                        ->whereRaw($hasDebtSql);
                } else {
                    $query->whereRaw('1=0');
                }
                break;

            case 'exempted':
                // Filtro a nivel de estudiantes: se aplica fuera del subquery de balances.
                break;

            case 'up_to_date':
                $query->whereRaw("NOT ($hasDebtSql)");
                break;
        }
    }

    private function getDebtSumSql(): string
    {
        $debtVal = BalanceStudentStatusEnum::Debt->value;
        $partialVal = BalanceStudentStatusEnum::PartiallyPaid->value;

        $parts = ['(CASE WHEN inscription < 0 THEN ABS(inscription) ELSE 0 END)'];

        foreach (self::MONTHS as $month) {
            $parts[] = "(CASE WHEN $month < 0 AND {$month}_status IN ('$debtVal', '$partialVal') THEN ABS($month) ELSE 0 END)";
        }

        return implode(' + ', $parts);
    }
}
