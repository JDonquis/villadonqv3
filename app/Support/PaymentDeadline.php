<?php

namespace App\Support;

use Carbon\Carbon;

class PaymentDeadline
{
    public static function currentMonthPastDue(int $dayOfMonthlyPayment, int $gracePeriod): bool
    {
        $deadline = Carbon::create(now()->year, now()->month, max(1, $dayOfMonthlyPayment))
            ->addDays($gracePeriod);

        return now()->gte($deadline);
    }
}
