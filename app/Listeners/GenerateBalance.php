<?php

namespace App\Listeners;

use App\Enums\BalanceStudentStatusEnum;
use App\Models\MainConfig;
use App\Models\SchoolLapse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateBalance
{
    private $months = [
        'september' => 0,
        'october' => 0,
        'november' => 0,
        'december' => 0,
        'january' => 0,
        'february' => 0,
        'march' => 0,
        'april' => 0,
        'may' => 0,
        'june' => 0,
        'july' => 0,
        'august' => 0,
    ];

    private $monthStatuses = [];

    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $student = $event->student;

        $configData = MainConfig::select('new_inscription_price', 'monthly_payment')->first();
        $schoolLapseActive = SchoolLapse::where('status', 1)->first();

        $effectiveMonthlyPayment = (float) $configData->monthly_payment;
        $effectiveInscriptionPrice = (float) $configData->new_inscription_price;

        if ($student->is_exempt && $student->exemption_percentage) {
            $multiplier = 1 - ($student->exemption_percentage / 100);
            $effectiveMonthlyPayment *= $multiplier;
            $effectiveInscriptionPrice *= $multiplier;
        }

        $currentDate = Carbon::now();
        // $currentDate->month(9);
        $currentMonthName = strtolower($currentDate->englishMonth);
        $setValue = false;

        foreach ($this->months as $monthName => $value) {
            if ($monthName == $currentMonthName) {
                $setValue = true;
                $this->monthStatuses[$monthName] = BalanceStudentStatusEnum::Debt->value;
                $this->months[$monthName] = $this->months[$monthName] - $effectiveMonthlyPayment;
            } elseif ($setValue) {
                $this->monthStatuses[$monthName] = BalanceStudentStatusEnum::Pending->value;
                $this->months[$monthName] = $this->months[$monthName] - $effectiveMonthlyPayment;
            } else {
                $this->monthStatuses[$monthName] = BalanceStudentStatusEnum::Paid->value;
            }
        }

        DB::table('balance_students')->insert(
            [
                'status' => BalanceStudentStatusEnum::Debt->value,
                'student_id' => $student->id,
                'school_lapse_id' => $schoolLapseActive->id,
                'inscription' => -$effectiveInscriptionPrice,
                'september' => $this->months['september'],
                'september_status' => $this->monthStatuses['september'],
                'october' => $this->months['october'],
                'october_status' => $this->monthStatuses['october'],
                'november' => $this->months['november'],
                'november_status' => $this->monthStatuses['november'],
                'december' => $this->months['december'],
                'december_status' => $this->monthStatuses['december'],
                'january' => $this->months['january'],
                'january_status' => $this->monthStatuses['january'],
                'february' => $this->months['february'],
                'february_status' => $this->monthStatuses['february'],
                'march' => $this->months['march'],
                'march_status' => $this->monthStatuses['march'],
                'april' => $this->months['april'],
                'april_status' => $this->monthStatuses['april'],
                'may' => $this->months['may'],
                'may_status' => $this->monthStatuses['may'],
                'june' => $this->months['june'],
                'june_status' => $this->monthStatuses['june'],
                'july' => $this->months['july'],
                'july_status' => $this->monthStatuses['july'],
                'august' => $this->months['august'],
                'august_status' => $this->monthStatuses['august'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),

            ]
        );
    }
}
