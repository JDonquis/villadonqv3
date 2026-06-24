<?php

namespace App\Services;

use App\Enums\BalanceStudentStatusEnum;
use App\Models\BalancePayment;
use App\Models\BalanceStudent;
use App\Models\MainConfig;
use App\Models\Payment;
use App\Models\Student;
use Carbon\Carbon;
use Exception;

class BalanceService
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

    public function updateStudentBalance(Payment $payment, Student $student, array $balances): void
    {
        $sortedBalances = collect($balances)
            ->sortBy('id')
            ->values();

        $amount = $payment->students()
            ->where('student_id', $student->id)
            ->first()
            ->pivot->amount_in_dolars;

        $remainingAmount = $amount;

        $config = MainConfig::select('monthly_payment', 'day_of_monthly_payment')->first();
        $basePrice = (float) ($config->monthly_payment ?? 0);
        $dayOfMonthlyPayment = $config->day_of_monthly_payment ?? 1;
        
        $multiplier = $student->is_exempt ? (1 - (($student->exemption_percentage ?? 0) / 100)) : 1;
        $effectivePrice = $basePrice * $multiplier;

        $currentMonthName = strtolower(Carbon::now()->englishMonth);
        $monthOrder = array_flip(self::MONTH_ORDER);
        $currentMonthIndex = $monthOrder[$currentMonthName] ?? -1;

        foreach ($sortedBalances as $balanceData) {
            if ($remainingAmount <= 0) {
                break;
            }

            $balance = BalanceStudent::find($balanceData['id']);

            if (! $balance) {
                continue;
            }

            if ($balance->inscription < 0) {
                $inscriptionDebt = abs($balance->inscription);

                $inscriptionPaymentsCount = BalancePayment::where('balance_student_id', $balance->id)
                    ->where('is_inscription', true)
                    ->count();

                if ($remainingAmount < $inscriptionDebt) {
                    if ($inscriptionPaymentsCount >= 2) {
                        throw new Exception(
                            "La inscripción debe ser cancelada en un máximo de 3 pagos. Este es el tercer intento y el monto ({$remainingAmount}$) no cubre la deuda restante ({$inscriptionDebt}$) para el estudiante {$student->name} {$student->last_name}."
                        );
                    }

                    $paymentToInscription = $remainingAmount;
                    $balance->inscription += $paymentToInscription;
                    $balance->inscription_status = BalanceStudentStatusEnum::PartiallyPaid->value;
                } else {
                    $paymentToInscription = $inscriptionDebt;
                    $balance->inscription += $paymentToInscription;
                    $balance->inscription_status = BalanceStudentStatusEnum::Paid->value;
                }

                BalancePayment::create([
                    'payment_id' => $payment->id,
                    'balance_student_id' => $balance->id,
                    'amount' => $paymentToInscription,
                    'month' => null,
                    'is_inscription' => true,
                ]);

                $remainingAmount -= $paymentToInscription;
            }

            foreach (self::MONTH_ORDER as $index => $month) {
                if ($remainingAmount <= 0) {
                    break;
                }

                if ($balance->$month < 0) {
                    $monthDebt = abs($balance->$month);
                    $paymentToMonth = min($remainingAmount, $monthDebt);

                    $balance->$month += $paymentToMonth;

                    $monthValue = $balance->$month;
                    $balance->{$month . '_status'} = $this->determineMonthStatus(
                        $monthValue, 
                        $effectivePrice, 
                        $index, 
                        $currentMonthIndex, 
                        $dayOfMonthlyPayment
                    );

                    BalancePayment::create([
                        'payment_id' => $payment->id,
                        'balance_student_id' => $balance->id,
                        'amount' => $paymentToMonth,
                        'month' => $month,
                        'is_inscription' => false,
                    ]);

                    $remainingAmount -= $paymentToMonth;
                }
            }

            $this->updateGeneralStatus($balance);
            $balance->save();
        }
    }

    public function revertStudentBalance(Payment $payment, Student $student): void
    {
        $balancePayments = BalancePayment::where('payment_id', $payment->id)
            ->whereHas('balanceStudent', function ($query) use ($student) {
                $query->where('student_id', $student->id);
            })
            ->get();

        $groupedByBalance = $balancePayments->groupBy('balance_student_id');

        $config = MainConfig::select('monthly_payment', 'day_of_monthly_payment')->first();
        $basePrice = (float) ($config->monthly_payment ?? 0);
        $dayOfMonthlyPayment = $config->day_of_monthly_payment ?? 1;
        
        $multiplier = $student->is_exempt ? (1 - (($student->exemption_percentage ?? 0) / 100)) : 1;
        $effectivePrice = $basePrice * $multiplier;

        $currentMonthName = strtolower(Carbon::now()->englishMonth);
        $monthOrder = array_flip(self::MONTH_ORDER);
        $currentMonthIndex = $monthOrder[$currentMonthName] ?? -1;

        foreach ($groupedByBalance as $balanceId => $bps) {
            $balance = BalanceStudent::find($balanceId);
            if (! $balance) {
                continue;
            }

            foreach ($bps as $balancePayment) {
                if ($balancePayment->is_inscription) {
                    $balance->inscription -= $balancePayment->amount;
                } else {
                    $month = $balancePayment->month;
                    if ($month) {
                        $balance->$month -= $balancePayment->amount;
                    }
                }
                $balancePayment->delete();
            }

            $inscriptionValue = $balance->inscription;
            $balance->inscription_status = match (true) {
                $inscriptionValue == 0 => BalanceStudentStatusEnum::Paid->value,
                $inscriptionValue < 0 => BalanceStudentStatusEnum::Debt->value,
                default => BalanceStudentStatusEnum::PartiallyPaid->value,
            };

            foreach (self::MONTH_ORDER as $index => $month) {
                $monthValue = $balance->$month;
                $balance->{$month . '_status'} = $this->determineMonthStatus(
                    $monthValue, 
                    $effectivePrice, 
                    $index, 
                    $currentMonthIndex, 
                    $dayOfMonthlyPayment
                );
            }

            $this->updateGeneralStatus($balance);
            $balance->save();
        }
    }

    public function recalculateBalanceForExemption(Student $student, float $exemptionPercentage, bool $applyToPastDebts): void
    {
        $multiplier = 1 - ($exemptionPercentage / 100);
        $config = MainConfig::select('monthly_payment', 'new_inscription_price', 'day_of_monthly_payment')->first();
        
        $baseMonthlyPayment = (float) ($config->monthly_payment ?? 0);
        $baseInscriptionPrice = (float) ($config->new_inscription_price ?? 0);
        $dayOfMonthlyPayment = $config->day_of_monthly_payment ?? 1;

        $newEffectiveMonthlyPrice = $baseMonthlyPayment * $multiplier;
        $newEffectiveInscriptionPrice = $baseInscriptionPrice * $multiplier;

        $currentMonthName = strtolower(Carbon::now()->englishMonth);
        $monthOrder = array_flip(self::MONTH_ORDER);
        $currentMonthIndex = $monthOrder[$currentMonthName] ?? -1;

        $balances = BalanceStudent::where('student_id', $student->id)->get();

        foreach ($balances as $balance) {
            $balancePayments = BalancePayment::where('balance_student_id', $balance->id)->get();
            $paymentsByMonth = $balancePayments->groupBy('month');
            $totalPaidInscription = $balancePayments->where('is_inscription', true)->sum('amount');

            foreach (self::MONTH_ORDER as $index => $month) {
                if (! $applyToPastDebts) {
                    if ($index < $currentMonthIndex && $currentMonthIndex !== -1) {
                        continue;
                    }
                }

                $totalPaid = (float) (($paymentsByMonth->get($month, collect()))->sum('amount'));
                
                // Recalcular balance basado en el nuevo precio efectivo
                $balance->$month = $totalPaid - $newEffectiveMonthlyPrice;
                $balance->{$month . '_status'} = $this->determineMonthStatus(
                    (float) $balance->$month, 
                    $newEffectiveMonthlyPrice,
                    $index,
                    $currentMonthIndex,
                    $dayOfMonthlyPayment
                );
            }

            // Inscripción (solo si aplica a deudas pasadas o si aún no está pagada totalmente y queremos ajustarla)
            // Normalmente la inscripción es al inicio, pero si applyToPastDebts es true, la ajustamos.
            if ($applyToPastDebts) {
                $balance->inscription = $totalPaidInscription - $newEffectiveInscriptionPrice;
                $inscriptionValue = (float) $balance->inscription;
                $balance->inscription_status = match (true) {
                    $inscriptionValue >= 0 => BalanceStudentStatusEnum::Paid->value,
                    $inscriptionValue > ($newEffectiveInscriptionPrice * -1) => BalanceStudentStatusEnum::PartiallyPaid->value,
                    default => BalanceStudentStatusEnum::Debt->value,
                };
            }

            $this->updateGeneralStatus($balance);
            $balance->save();
        }
    }

    private function determineMonthStatus(float $monthValue, float $effectivePrice, int $monthIndex, int $currentMonthIndex, int $dayOfMonthlyPayment): string
    {
        if ($monthValue >= 0) {
            return BalanceStudentStatusEnum::Paid->value;
        }

        $fullDebtAmount = $effectivePrice * -1;

        if ($monthValue > $fullDebtAmount) {
            return BalanceStudentStatusEnum::PartiallyPaid->value;
        }

        if ($monthIndex > $currentMonthIndex) {
            return BalanceStudentStatusEnum::Pending->value;
        }

        if ($monthIndex < $currentMonthIndex) {
            return BalanceStudentStatusEnum::Debt->value;
        }

        return Carbon::now()->day >= $dayOfMonthlyPayment
            ? BalanceStudentStatusEnum::Debt->value
            : BalanceStudentStatusEnum::Pending->value;
    }

    private function updateGeneralStatus(BalanceStudent $balance): void
    {
        $statuses = [];

        $inscriptionStatus = $balance->inscription_status;
        if ($inscriptionStatus) {
            $statuses[] = $inscriptionStatus instanceof BalanceStudentStatusEnum 
                ? $inscriptionStatus->value 
                : $inscriptionStatus;
        }

        foreach (self::MONTH_ORDER as $month) {
            $statusField = $month . '_status';
            $monthStatus = $balance->$statusField;
            if ($monthStatus) {
                $statuses[] = $monthStatus instanceof BalanceStudentStatusEnum
                    ? $monthStatus->value
                    : $monthStatus;
            }
        }

        $allPaid = collect($statuses)->every(
            fn($status) => $status === BalanceStudentStatusEnum::Paid->value
        );

        if ($allPaid) {
            $balance->status = BalanceStudentStatusEnum::Paid->value;

            return;
        }

        $hasDebt = collect($statuses)->contains(
            fn($status) => $status === BalanceStudentStatusEnum::Debt->value
        );

        if ($hasDebt) {
            $balance->status = BalanceStudentStatusEnum::Debt->value;

            return;
        }

        $hasPartial = collect($statuses)->contains(
            fn($status) => $status === BalanceStudentStatusEnum::PartiallyPaid->value
        );

        $balance->status = $hasPartial
            ? BalanceStudentStatusEnum::PartiallyPaid->value
            : BalanceStudentStatusEnum::Pending->value;
    }
}
