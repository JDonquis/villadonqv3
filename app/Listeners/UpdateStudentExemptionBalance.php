<?php

namespace App\Listeners;

use App\Events\StudentUpdated;
use App\Services\BalanceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateStudentExemptionBalance
{
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
    public function handle(StudentUpdated $event): void
    {
        $student = $event->student;
        $previousExemptData = $event->previousExemptData;

        if (!$previousExemptData) {
            return;
        }

        $exemptionChanged = (
            (bool) $previousExemptData['is_exempt'] !== (bool) $student->is_exempt
            || (float) ($previousExemptData['exemption_percentage'] ?? 0) !== (float) ($student->exemption_percentage ?? 0)
        );

        if ($exemptionChanged) {
            $exemptionPercentage = $student->is_exempt ? (float) ($student->exemption_percentage ?? 0) : 0;
            $applyToPastDebts = (bool) ($student->apply_to_past_debts ?? false);

            (new BalanceService())->recalculateBalanceForExemption(
                $student,
                $exemptionPercentage,
                $applyToPastDebts
            );
        }
    }
}
