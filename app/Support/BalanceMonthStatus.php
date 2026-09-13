<?php

namespace App\Support;

use App\Enums\BalanceStudentStatusEnum;
use App\Models\BalanceStudent;
use App\Models\SchoolLapse;

class BalanceMonthStatus
{
    public const PAST = 'past';

    public const CURRENT = 'current';

    public const FUTURE = 'future';

    /**
     * Determina el estatus de un mes según su valor, el precio efectivo del
     * estudiante y si el mes ya venció. Un mes no vencido nunca es deuda:
     * como mucho queda "pending" (aunque tenga un abono parcial).
     */
    public static function determine(float $monthValue, float $effectivePrice, bool $isDue): string
    {
        if ($monthValue >= 0) {
            return BalanceStudentStatusEnum::Paid->value;
        }

        if (! $isDue) {
            return BalanceStudentStatusEnum::Pending->value;
        }

        $fullDebt = $effectivePrice * -1;

        return $monthValue > $fullDebt
            ? BalanceStudentStatusEnum::PartiallyPaid->value
            : BalanceStudentStatusEnum::Debt->value;
    }

    /**
     * Indica si un mes ya venció según su posición dentro del lapso escolar.
     */
    public static function isDue(
        int $monthIndex,
        int $currentMonthIndex,
        bool $currentMonthPastDue,
        string $lapsePosition,
    ): bool {
        if ($lapsePosition === self::PAST) {
            return true;
        }

        if ($lapsePosition === self::FUTURE) {
            return false;
        }

        return $monthIndex < $currentMonthIndex
            || ($monthIndex === $currentMonthIndex && $currentMonthPastDue);
    }

    /**
     * Posición del lapso del balance respecto al lapso activo.
     */
    public static function lapsePosition(BalanceStudent $balance, ?SchoolLapse $currentLapse): string
    {
        if (! $currentLapse) {
            return self::CURRENT;
        }

        if ((int) $balance->school_lapse_id === (int) $currentLapse->id) {
            return self::CURRENT;
        }

        $lapseStart = $balance->schoolLapse?->start;

        if ($lapseStart && $lapseStart < $currentLapse->start) {
            return self::PAST;
        }

        return self::FUTURE;
    }
}
