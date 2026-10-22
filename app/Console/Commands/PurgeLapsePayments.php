<?php

namespace App\Console\Commands;

use App\Services\PaymentPurgeService;
use Illuminate\Console\Command;
use Throwable;

class PurgeLapsePayments extends Command
{
    protected $signature = 'payments:purge-lapse
        {--lapse= : ID del período escolar (por defecto el activo)}
        {--only-linked : Solo pagos vinculados a balances o cargos del período}
        {--dry-run : Solo previsualizar, no elimina nada}
        {--force : No pedir confirmación}';

    protected $description = 'Elimina físicamente los pagos de un período escolar y revierte sus efectos en los estados de cuenta.';

    public function handle(PaymentPurgeService $service): int
    {
        $lapseId = $this->option('lapse') ? (int) $this->option('lapse') : null;

        try {
            $lapse = $service->resolveLapse($lapseId);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $onlyLinked = (bool) $this->option('only-linked');
        $preview = $service->preview($lapse, $onlyLinked);

        $this->info("Período #{$lapse->id} ({$lapse->start} → {$lapse->end})");

        if ($preview['payments_count'] === 0) {
            $this->warn('No hay pagos asociados a este período.');

            return self::SUCCESS;
        }

        $this->table(
            ['Pagos', 'Activos', 'Ya borrados', 'Estudiantes', 'Balances', 'Cargos', 'Total USD', 'Total Bs'],
            [[
                $preview['payments_count'],
                $preview['active_count'],
                $preview['already_deleted_count'],
                $preview['students_count'],
                $preview['balances_count'],
                $preview['charges_count'],
                number_format($preview['total_in_dolars'], 2, ',', '.'),
                number_format($preview['total_in_bs'], 2, ',', '.'),
            ]]
        );

        $this->table(
            ['ID', 'Fecha', 'Estudiante(s)', 'Concepto', 'Método', 'USD', 'Estado'],
            array_map(fn (array $row) => [
                $row['id'],
                $row['date'],
                $row['students'] ?: '—',
                $row['concept'],
                $row['method'],
                number_format($row['total_in_dolars'], 2, ',', '.'),
                $row['status'] === 1 ? 'activo' : 'borrado',
            ], $preview['sample'])
        );

        if ($preview['payments_count'] > count($preview['sample'])) {
            $this->line('... y '.($preview['payments_count'] - count($preview['sample'])).' pago(s) más.');
        }

        if (! $preview['only_linked']) {
            $this->line('Incluye pagos sin vínculo contable cuya fecha cae en el período. Use --only-linked para excluirlos.');
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run: no se eliminó nada.');

            return self::SUCCESS;
        }

        $this->warn('Esta acción borra las filas de pagos, aplicaciones y desgloses. No es reversible.');

        if (! $this->option('force') && ! $this->confirm('¿Eliminar definitivamente estos pagos y revertir los balances?', false)) {
            $this->line('Cancelado.');

            return self::SUCCESS;
        }

        $result = $service->purge($lapse, $onlyLinked);

        $this->info("Pagos eliminados: {$result['payments_deleted']}");
        $this->table(
            ['Activos revertidos', 'Ya borrados', 'Balances afectados', 'Cargos afectados', 'Total USD revertido'],
            [[
                $result['active_reverted'],
                $result['already_deleted'],
                $result['balances_reverted'],
                $result['charges_reverted'],
                number_format($result['total_in_dolars'], 2, ',', '.'),
            ]]
        );

        return self::SUCCESS;
    }
}
