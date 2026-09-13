<?php

namespace App\Console\Commands;

use App\Enums\BalanceStudentStatusEnum;
use App\Models\BalanceStudent;
use App\Models\MainConfig;
use App\Models\SchoolLapse;
use App\Support\BalanceMonthStatus;
use App\Support\PaymentDeadline;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RecalculateBalanceStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'balance:recalculate-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalcula el estatus de los balances de los estudiantes según la fecha de vencimiento y periodos de gracia.';

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

    /**
     * Execute the console command.
     */
    public function handle()
    {

        Log::info('Iniciando comando balance:recalculate-status');

        $this->info('Iniciando recalculación de estatus de balances...');

        $config = MainConfig::first();
        if (! $config) {
            $this->error('No se encontró configuración principal (MainConfig).');

            return 1;
        }

        $dayOfPayment = $config->day_of_monthly_payment ?? 5;
        $gracePeriod = $config->grace_period ?? 0;

        $currentLapse = SchoolLapse::where('status', 1)->first();
        $now = Carbon::now();
        $currentMonthName = strtolower($now->format('F'));
        $currentMonthIndex = array_search($currentMonthName, self::SCHOOL_MONTHS);
        $isPastDueDate = PaymentDeadline::currentMonthPastDue($dayOfPayment, $gracePeriod);

        // Solo procesamos balances que NO estén marcados como Paid (pagados completamente)
        $balances = BalanceStudent::where('status', '!=', BalanceStudentStatusEnum::Paid->value)
            ->with(['student', 'schoolLapse'])
            ->get();

        $count = 0;
        foreach ($balances as $balance) {
            $changed = false;
            $student = $balance->student;

            if (! $student) {
                continue;
            }

            $multiplier = $student->is_exempt ? (1 - (($student->exemption_percentage ?? 0) / 100)) : 1;
            $effectivePrice = (float) ($config->monthly_payment ?? 0) * $multiplier;
            $lapsePosition = BalanceMonthStatus::lapsePosition($balance, $currentLapse);

            // Recalcular estatus de meses
            foreach (self::SCHOOL_MONTHS as $index => $month) {
                $statusField = $month.'_status';
                $value = (float) $balance->$month;

                $isDue = BalanceMonthStatus::isDue($index, $currentMonthIndex, $isPastDueDate, $lapsePosition);
                $newStatus = BalanceMonthStatus::determine($value, $effectivePrice, $isDue);

                $oldValue = $balance->$statusField instanceof BalanceStudentStatusEnum
                    ? $balance->$statusField->value
                    : $balance->$statusField;

                if ($oldValue !== $newStatus) {
                    $balance->$statusField = $newStatus;
                    $changed = true;
                }
            }

            // Recalcular Estatus General del Balance
            if ($changed) {
                $this->updateGeneralStatus($balance);
                $balance->save();
                $count++;
            }
        }

        $this->info("Recalculación completada. Se actualizaron {$count} balances.");
    }

    private function updateGeneralStatus(BalanceStudent $balance): void
    {
        $statuses = [];
        if ($balance->inscription_status) {
            $statuses[] = $balance->inscription_status instanceof BalanceStudentStatusEnum
                ? $balance->inscription_status->value
                : $balance->inscription_status;
        }

        foreach (self::SCHOOL_MONTHS as $month) {
            $statusField = $month.'_status';
            $statuses[] = $balance->$statusField instanceof BalanceStudentStatusEnum
                ? $balance->$statusField->value
                : $balance->$statusField;
        }

        $allPaid = collect($statuses)->every(fn ($status) => $status === BalanceStudentStatusEnum::Paid->value);
        if ($allPaid) {
            $balance->status = BalanceStudentStatusEnum::Paid->value;

            return;
        }

        $hasDebt = collect($statuses)->contains(fn ($status) => $status === BalanceStudentStatusEnum::Debt->value);
        if ($hasDebt) {
            $balance->status = BalanceStudentStatusEnum::Debt->value;

            return;
        }

        $hasPartial = collect($statuses)->contains(fn ($status) => $status === BalanceStudentStatusEnum::PartiallyPaid->value);
        $balance->status = $hasPartial
            ? BalanceStudentStatusEnum::PartiallyPaid->value
            : BalanceStudentStatusEnum::Pending->value;
    }
}
