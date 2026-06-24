<?php

namespace App\Console\Commands;

use App\Enums\BalanceStudentStatusEnum;
use App\Models\BalanceStudent;
use App\Models\MainConfig;
use App\Models\SchoolLapse;
use App\Models\Student;
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

        Log::info("Iniciando comando balance:recalculate-status");

        $this->info('Iniciando recalculación de estatus de balances...');

        $config = MainConfig::first();
        if (!$config) {
            $this->error('No se encontró configuración principal (MainConfig).');
            return 1;
        }

        $dayOfPayment = $config->day_of_monthly_payment ?? 5;
        $gracePeriod = $config->grace_period ?? 0;
        $dueDate = $dayOfPayment + $gracePeriod;

        $currentLapse = SchoolLapse::where('status', 1)->first();
        $now = Carbon::now();
        $currentMonthName = strtolower($now->format('F'));
        $currentMonthIndex = array_search($currentMonthName, self::SCHOOL_MONTHS);
        $isPastDueDate = $now->day >= $dueDate;

        // Solo procesamos balances que NO estén marcados como Paid (pagados completamente)
        $balances = BalanceStudent::where('status', '!=', BalanceStudentStatusEnum::Paid->value)
            ->with(['student', 'schoolLapse'])
            ->get();

        $count = 0;
        foreach ($balances as $balance) {
            $changed = false;
            $student = $balance->student;

            if (!$student) continue;

            // Recalcular estatus de meses
            foreach (self::SCHOOL_MONTHS as $index => $month) {
                $statusField = $month . '_status';
                $value = (float) $balance->$month;
                $currentStatus = $balance->$statusField;

                // Si ya está pagado o no tiene deuda, no cambiamos nada para ese mes
                if ($value >= 0) {
                    if ($currentStatus !== BalanceStudentStatusEnum::Paid->value) {
                        $balance->$statusField = BalanceStudentStatusEnum::Paid->value;
                        $changed = true;
                    }
                    continue;
                }

                // Si tiene deuda (valor < 0)
                $newStatus = $currentStatus;

                // Determinamos el nuevo estatus basado en el periodo
                if ($currentLapse && $balance->school_lapse_id === $currentLapse->id) {
                    // Meses futuros en el mismo lapso
                    if ($index > $currentMonthIndex) {
                        $newStatus = BalanceStudentStatusEnum::Pending->value;
                    }
                    // Meses pasados en el mismo lapso
                    elseif ($index < $currentMonthIndex) {
                        $newStatus = BalanceStudentStatusEnum::Debt->value;
                    }
                    // Mes actual
                    else {
                        $newStatus = $isPastDueDate
                            ? BalanceStudentStatusEnum::Debt->value
                            : BalanceStudentStatusEnum::Pending->value;
                    }
                }
                // Lapsos anteriores o sin lapso activo (todo lo pendiente es deuda)
                elseif ($balance->schoolLapse && $balance->schoolLapse->start < ($currentLapse->start ?? $now->toDateString())) {
                    $newStatus = BalanceStudentStatusEnum::Debt->value;
                } else {
                    // Si es un lapso futuro o no identificado, se mantiene como pendiente
                    $newStatus = BalanceStudentStatusEnum::Pending->value;
                }

                // Si es un pago parcial (tiene deuda pero algo se ha pagado), mantenemos PartiallyPaid
                // Nota: Asumimos que si hay deuda pero el valor es mayor a -precio_completo, es parcial.
                // Sin embargo, para simplificar y seguir la lógica de BalanceService,
                // solo lo cambiamos si no es ya PartiallyPaid o si el valor indica deuda total.
                // Ajuste: Si el valor es negativo, verificamos si es deuda completa o parcial.

                // Obtenemos el precio efectivo para este estudiante (opcional si queremos ser muy precisos)
                // Pero basándonos en tu requerimiento de "verificar fechas y grace period":
                if ($currentStatus === BalanceStudentStatusEnum::PartiallyPaid->value) {
                    // No cambiamos PartiallyPaid a Pending/Debt a menos que sea necesario,
                    // pero el requerimiento se enfoca en Debt vs Pending por fechas.
                }

                if ($balance->$statusField instanceof BalanceStudentStatusEnum) {
                    $oldValue = $balance->$statusField->value;
                } else {
                    $oldValue = $balance->$statusField;
                }

                if ($oldValue !== $newStatus && $oldValue !== BalanceStudentStatusEnum::PartiallyPaid->value) {
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
            $statusField = $month . '_status';
            $statuses[] = $balance->$statusField instanceof BalanceStudentStatusEnum
                ? $balance->$statusField->value
                : $balance->$statusField;
        }

        $allPaid = collect($statuses)->every(fn($status) => $status === BalanceStudentStatusEnum::Paid->value);
        if ($allPaid) {
            $balance->status = BalanceStudentStatusEnum::Paid->value;
            return;
        }

        $hasDebt = collect($statuses)->contains(fn($status) => $status === BalanceStudentStatusEnum::Debt->value);
        if ($hasDebt) {
            $balance->status = BalanceStudentStatusEnum::Debt->value;
            return;
        }

        $hasPartial = collect($statuses)->contains(fn($status) => $status === BalanceStudentStatusEnum::PartiallyPaid->value);
        $balance->status = $hasPartial
            ? BalanceStudentStatusEnum::PartiallyPaid->value
            : BalanceStudentStatusEnum::Pending->value;
    }
}
