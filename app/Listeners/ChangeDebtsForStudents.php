<?php

namespace App\Listeners;

use App\Enums\BalanceStudentStatusEnum;
use App\Models\BalanceStudent;
use App\Models\MainConfig;
use App\Models\SchoolLapse;
use App\Support\PaymentDeadline;
use Carbon\Carbon;

class ChangeDebtsForStudents
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

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $newPrice = $event->newPrice;
        $activeLapse = SchoolLapse::where('status', 1)->first();

        if (! $activeLapse) {
            return;
        }

        $currentMonthName = strtolower(Carbon::now()->englishMonth);
        $monthOrder = array_flip(self::MONTH_ORDER);
        $currentMonthIndex = $monthOrder[$currentMonthName] ?? -1;

        $config = MainConfig::select('day_of_monthly_payment', 'grace_period')->first();
        $dayOfMonthlyPayment = $config->day_of_monthly_payment ?? 1;
        $gracePeriod = $config->grace_period ?? 0;

        // Recuperar todos los balances del periodo escolar activo
        $balances = BalanceStudent::where('school_lapse_id', $activeLapse->id)
            ->with(['student', 'balancePayments'])
            ->get();

        foreach ($balances as $balance) {
            $student = $balance->student;

            // Calcular precio efectivo basado en exoneración del estudiante
            $exemptionPercentage = $student->is_exempt ? ($student->exemption_percentage ?? 0) : 0;
            $multiplier = 1 - ($exemptionPercentage / 100);
            $effectivePrice = $newPrice * $multiplier;

            $paymentsByMonth = $balance->balancePayments->groupBy('month');

            foreach (self::MONTH_ORDER as $index => $month) {
                // Solo actualizar a partir del mes actual
                if ($index < $currentMonthIndex && $currentMonthIndex !== -1) {
                    continue;
                }

                // Sumar todos los pagos realizados para este mes específico
                $totalPaid = (float) (($paymentsByMonth->get($month, collect()))->sum('amount'));

                // Recalcular balance: Pago Total - Precio Efectivo
                $newBalanceValue = $totalPaid - $effectivePrice;

                $balance->$month = $newBalanceValue;
                $balance->{$month.'_status'} = $this->determineMonthStatus($newBalanceValue, $effectivePrice, $index, $currentMonthIndex, $dayOfMonthlyPayment, $gracePeriod);
            }

            $this->updateGeneralStatus($balance);
            $balance->save();
        }
    }

    /**
     * Determina el estado de un mes específico basándose en el balance y la fecha actual.
     */
    private function determineMonthStatus(float $monthValue, float $effectivePrice, int $monthIndex, int $currentMonthIndex, int $dayOfMonthlyPayment, int $gracePeriod = 0): string
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

        // mes actual (monthIndex == currentMonthIndex)
        return PaymentDeadline::currentMonthPastDue($dayOfMonthlyPayment, $gracePeriod)
            ? BalanceStudentStatusEnum::Debt->value
            : BalanceStudentStatusEnum::Pending->value;
    }

    /**
     * Actualiza el estado general del registro de balance del estudiante.
     */
    private function updateGeneralStatus(BalanceStudent $balance): void
    {
        $statuses = [];

        // Estado de inscripción
        if ($balance->inscription_status) {
            $statuses[] = $balance->inscription_status instanceof BalanceStudentStatusEnum
                ? $balance->inscription_status->value
                : $balance->inscription_status;
        }

        // Estados mensuales
        foreach (self::MONTH_ORDER as $month) {
            $statusField = $month.'_status';
            $val = $balance->$statusField;
            $statuses[] = $val instanceof BalanceStudentStatusEnum ? $val->value : $val;
        }

        $allPaid = collect($statuses)->every(
            fn ($status) => $status === BalanceStudentStatusEnum::Paid->value
        );

        if ($allPaid) {
            $balance->status = BalanceStudentStatusEnum::Paid->value;

            return;
        }

        $hasDebt = collect($statuses)->contains(
            fn ($status) => $status === BalanceStudentStatusEnum::Debt->value
        );

        if ($hasDebt) {
            $balance->status = BalanceStudentStatusEnum::Debt->value;

            return;
        }

        $hasPartial = collect($statuses)->contains(
            fn ($status) => $status === BalanceStudentStatusEnum::PartiallyPaid->value
        );

        $balance->status = $hasPartial
            ? BalanceStudentStatusEnum::PartiallyPaid->value
            : BalanceStudentStatusEnum::Pending->value;
    }
}
