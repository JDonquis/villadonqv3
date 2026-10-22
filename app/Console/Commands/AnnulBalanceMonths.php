<?php

namespace App\Console\Commands;

use App\Services\BalanceMaintenanceService;
use Illuminate\Console\Command;

class AnnulBalanceMonths extends Command
{
    protected $signature = 'balances:annul-months
        {--lapse= : ID del periodo escolar (por defecto el activo)}
        {--up-to= : Mes hasta el cual anular (september..august) o "none" para solo inscripción}
        {--with-inscription : Anular también la inscripción}
        {--course= : Filtro por curso}
        {--section= : Filtro por sección}
        {--dry-run : Solo previsualizar}';

    protected $description = 'Anula (deja en "No aplica") los meses hasta el indicado para los balances de un periodo.';

    public function handle(): int
    {
        $upToOption = $this->option('up-to');
        $upTo = $upToOption === 'none' ? null : $upToOption;

        if ($upTo !== null && ! in_array($upTo, BalanceMaintenanceService::MONTHS, true)) {
            $this->error('Debe indicar --up-to con un mes válido ('.implode(', ', BalanceMaintenanceService::MONTHS).') o "none" para solo inscripción.');

            return self::FAILURE;
        }

        if ($upTo === null && ! $this->option('with-inscription')) {
            $this->error('Si --up-to es "none", debe indicar --with-inscription.');

            return self::FAILURE;
        }

        $lapseId = $this->option('lapse') ? (int) $this->option('lapse') : null;
        $includeInscription = (bool) $this->option('with-inscription');
        $filters = [
            'course_id' => $this->option('course') ?: null,
            'section_id' => $this->option('section') ?: null,
        ];

        $service = new BalanceMaintenanceService;

        if ($this->option('dry-run')) {
            $preview = $service->preview($lapseId, $upTo, $includeInscription, $filters);
            $this->table(
                ['Afectados', 'Omitidos (con pagos)', 'Total a anular ($)'],
                [[$preview['affected_count'], $preview['skipped_count'], number_format($preview['total_annulled'], 2, ',', '.')]]
            );

            return self::SUCCESS;
        }

        $scope = $upTo !== null ? "hasta {$upTo}" : 'solo inscripción';

        if (! $this->confirm("¿Anular {$scope}?".($includeInscription && $upTo !== null ? ' (incluye inscripción)' : ''), false)) {
            return self::SUCCESS;
        }

        $result = $service->annul($lapseId, $upTo, $includeInscription, $filters);

        $this->info("Anulación #{$result['annulment_id']} aplicada.");
        $this->table(
            ['Afectados', 'Omitidos (con pagos)', 'Total anulado ($)'],
            [[$result['affected_count'], $result['skipped_count'], number_format($result['total_annulled'], 2, ',', '.')]]
        );

        return self::SUCCESS;
    }
}
