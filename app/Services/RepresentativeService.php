<?php

namespace App\Services;

use App\Models\Representative;
use App\Models\Student;
use App\Models\User;

class RepresentativeService
{
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
            ->with('course', 'section')
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
        $students = $this->getStudents($user);

        return [
            'students' => $students->map(fn ($student) => $this->formatStudent($student))->values(),
        ];
    }

    public function misPagos(User $user): array
    {
        $students = $this->getStudents($user)
            ->load(['balances.schoolLapse', 'balances.balancePayments.payment.accountPayment.method']);

        return [
            'students' => $students->map(function ($student) {
                return array_merge($this->formatStudent($student), [
                    'balances' => $student->balances->map(function ($balance) {
                        return [
                            'id' => $balance->id,
                            'status' => $balance->status,
                            'school_lapse' => $balance->schoolLapse
                                ? $balance->schoolLapse->start.' - '.$balance->schoolLapse->end
                                : null,
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

    private function formatStudent($student): array
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
