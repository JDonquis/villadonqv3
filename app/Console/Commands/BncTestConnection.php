<?php

namespace App\Console\Commands;

use App\Services\Bnc\BncClient;
use Illuminate\Console\Command;
use Throwable;

class BncTestConnection extends Command
{
    protected $signature = 'bnc:test {--banks : Lista los bancos disponibles}';

    protected $description = 'Prueba la conexión con el BNC y muestra el estado de la configuración.';

    public function handle(BncClient $client): int
    {
        $this->table(['Configuración', 'Valor'], [
            ['BNC_ENABLED', config('services.bnc.enabled') ? 'true' : 'false'],
            ['Base URL', config('services.bnc.base_url')],
            ['ClientGUID', filled(config('services.bnc.client_guid')) ? 'configurado' : 'faltante'],
            ['MasterKey', filled(config('services.bnc.master_key')) ? 'configurada' : 'faltante'],
            ['ClientID', config('services.bnc.client_id') ?: 'se usará el RIF del plantel'],
            ['AccountNumber', config('services.bnc.account_number') ?: 'se usará la cuenta seleccionada'],
            ['WorkingKey en caché', $client->hasCachedWorkingKey() ? 'sí' : 'no'],
        ]);

        if (! $client->isConfigured()) {
            $this->warn('Faltan credenciales BNC. Configura BNC_CLIENT_GUID y BNC_MASTER_KEY.');

            return self::FAILURE;
        }

        try {
            $result = $client->testConnection();
            $this->info('Conexión BNC correcta.');
            $this->line('Login: '.($result['logged_at'] ?? '—'));

            if ($this->option('banks')) {
                $banks = $client->banks();
                $rows = collect($banks['data'] ?? [])
                    ->filter(fn ($bank) => is_array($bank))
                    ->map(fn ($bank) => [$bank['Code'] ?? '', $bank['Name'] ?? '', $bank['Services'] ?? ''])
                    ->values()
                    ->all();

                if ($rows !== []) {
                    $this->table(['Código', 'Banco', 'Servicios'], $rows);
                }
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Conexión BNC fallida: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
