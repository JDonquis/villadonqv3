<?php

namespace App\Console\Commands;

use App\Services\Bnc\BncClient;
use Illuminate\Console\Command;
use Throwable;

class BncRefreshWorkingKey extends Command
{
    protected $signature = 'bnc:refresh-working-key';

    protected $description = 'Obtiene una nueva WorkingKey del BNC para las operaciones del día.';

    public function handle(BncClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->warn('BNC no está configurado (BNC_ENABLED, BNC_CLIENT_GUID, BNC_MASTER_KEY).');

            return self::SUCCESS;
        }

        try {
            $client->logOn(true);

            $this->info('WorkingKey BNC renovada correctamente.');
            $this->line('Obtenida: '.($client->lastLogOnAt() ?? '—'));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('No se pudo renovar la WorkingKey BNC: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
