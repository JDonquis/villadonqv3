<?php

namespace App\Services\Bnc;

use App\Exceptions\BncException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class BncClient
{
    public function __construct(
        private BncCrypto $crypto = new BncCrypto
    ) {}

    public function isConfigured(): bool
    {
        return filled(config('services.bnc.client_guid'))
            && filled(config('services.bnc.master_key'));
    }

    public function canVerify(?string $accountNumber = null, ?string $clientId = null): bool
    {
        return $this->isConfigured()
            && filled($accountNumber ?: config('services.bnc.account_number'))
            && filled($clientId ?: config('services.bnc.client_id'));
    }

    public function lastLogOnAt(): ?string
    {
        return Cache::get($this->logOnCacheKey().'.logged_at');
    }

    public function hasCachedWorkingKey(): bool
    {
        return Cache::has($this->logOnCacheKey());
    }

    public function logOn(bool $force = false): string
    {
        $cacheKey = $this->logOnCacheKey();

        if (! $force && Cache::has($cacheKey)) {
            return (string) Cache::get($cacheKey);
        }

        $payload = ['ClientGUID' => (string) config('services.bnc.client_guid')];

        $response = $this->request('/api/Auth/LogOn', $payload, (string) config('services.bnc.master_key'));

        $workingKey = $response['data']['WorkingKey'] ?? null;

        if (! filled($workingKey)) {
            throw new BncException('El BNC no devolvió una WorkingKey válida.', 'NOWK', [
                'response' => $response,
            ]);
        }

        Cache::put($cacheKey, (string) $workingKey, now()->endOfDay());
        Cache::put($cacheKey.'.logged_at', now()->toIso8601String(), now()->endOfDay());

        return (string) $workingKey;
    }

    public function forgetWorkingKey(): void
    {
        Cache::forget($this->logOnCacheKey());
        Cache::forget($this->logOnCacheKey().'.logged_at');
    }

    public function validate(array $params): array
    {
        return $this->call('/api/Position/Validate', $params);
    }

    public function validateP2P(array $params): array
    {
        return $this->call('/api/Position/ValidateP2P', $params);
    }

    public function validateExistence(array $params): array
    {
        return $this->call('/api/Position/ValidateExistence', $params);
    }

    public function historyByDate(array $params): array
    {
        return $this->call('/api/Position/HistoryByDate', $params);
    }

    public function dailyHistory(array $params): array
    {
        return $this->call('/api/Position/DailyTransactionHistory', $params);
    }

    public function banks(): array
    {
        return $this->call('/api/Services/Banks', []);
    }

    public function exchangeRates(): array
    {
        return $this->call('/api/Services/ExchangeRates', []);
    }

    public function currentBalance(array $params): array
    {
        return $this->call('/api/Position/Current', $params);
    }

    public function testConnection(): array
    {
        $workingKey = $this->logOn(true);

        return [
            'ok' => true,
            'working_key_cached' => filled($workingKey),
            'logged_at' => $this->lastLogOnAt(),
            'base_url' => $this->baseUrl(),
        ];
    }

    private function call(string $endpoint, array $payload): array
    {
        $workingKey = $this->logOn();

        try {
            return $this->request($endpoint, $payload, $workingKey);
        } catch (BncException $e) {
            if ($e->bncCode !== 'EPIRWK') {
                throw $e;
            }

            $this->forgetWorkingKey();
            $workingKey = $this->logOn(true);

            return $this->request($endpoint, $payload, $workingKey);
        }
    }

    private function request(string $endpoint, array $payload, string $secret): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new BncException('No se pudo serializar la petición BNC.');
        }

        $wrapper = [
            'ClientGUID' => (string) config('services.bnc.client_guid'),
            'Reference' => str_replace('-', '', (string) Str::uuid()),
            'Value' => $this->crypto->encrypt($json, $secret),
            'Validation' => $this->crypto->hash($json),
            'swTestOperation' => (bool) config('services.bnc.sw_test_operation', false),
        ];

        try {
            $response = Http::withOptions([
                'verify' => (bool) config('services.bnc.verify_ssl', true),
            ])
                ->timeout(max(1, (int) config('services.bnc.timeout', 20)))
                ->acceptJson()
                ->asJson()
                ->post($this->baseUrl().$endpoint, $wrapper);
        } catch (Throwable $e) {
            throw new BncException('No se pudo conectar con el BNC: '.$e->getMessage(), 'NETWORK', [
                'endpoint' => $endpoint,
            ]);
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new BncException('Respuesta no válida del BNC (HTTP '.$response->status().').', 'HTTP'.$response->status(), [
                'endpoint' => $endpoint,
                'body' => Str::limit($response->body(), 500),
            ]);
        }

        $status = strtoupper((string) ($body['status'] ?? 'KO'));
        $message = (string) ($body['message'] ?? '');

        if ($status !== 'OK' || ! $response->successful()) {
            throw new BncException(
                $message !== '' ? $message : 'El BNC rechazó la petición.',
                $this->extractCode($message),
                ['endpoint' => $endpoint, 'status' => $status]
            );
        }

        $encrypted = $body['value'] ?? null;
        $decryptedJson = '';

        if (filled($encrypted)) {
            try {
                $decryptedJson = $this->crypto->decrypt((string) $encrypted, $secret);
            } catch (Throwable $e) {
                throw new BncException('No se pudo descifrar la respuesta del BNC: '.$e->getMessage(), 'DECRYPT', [
                    'endpoint' => $endpoint,
                ]);
            }

            $validation = $body['validation'] ?? null;

            if (filled($validation) && ! $this->crypto->verifyHash($decryptedJson, (string) $validation)) {
                $encryptedHash = hash('sha256', (string) $encrypted);

                if (! hash_equals(strtolower((string) $validation), $encryptedHash)) {
                    Log::warning('La firma de la respuesta BNC no coincide con el contenido descifrado.', [
                        'endpoint' => $endpoint,
                    ]);
                }
            }
        }

        $data = $decryptedJson !== '' ? json_decode($decryptedJson, true) : [];

        if (! is_array($data)) {
            $data = [];
        }

        return [
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'decrypted' => $decryptedJson,
        ];
    }

    private function extractCode(string $message): ?string
    {
        if (preg_match('/^([A-Z0-9]{2,8})/', $message, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.bnc.base_url'), '/');
    }

    private function logOnCacheKey(): string
    {
        return 'bnc.working_key.'.md5((string) config('services.bnc.client_guid'));
    }
}
