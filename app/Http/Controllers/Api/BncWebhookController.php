<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\BncWebhookAuthenticate;
use App\Jobs\ProcessBncNotification;
use App\Models\BncNotification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BncWebhookController extends Controller
{
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'OK',
            'message' => 'BNC webhook disponible',
        ]);
    }

    public function auth(Request $request)
    {
        $login = (string) ($request->input('Login') ?? $request->input('login') ?? '');
        $password = (string) ($request->input('Password') ?? $request->input('password') ?? '');

        $expectedLogin = (string) config('services.bnc.webhook.login');
        $expectedPassword = (string) config('services.bnc.webhook.password');

        if ($expectedLogin === '' || $expectedPassword === '') {
            return response('Autenticación JWT no configurada.', 400);
        }

        if (! hash_equals($expectedLogin, $login) || ! hash_equals($expectedPassword, $password)) {
            return response('Credenciales inválidas.', 400);
        }

        $token = Str::random(64);
        $ttl = max(60, (int) config('services.bnc.webhook.token_ttl', 3600));

        Cache::put(BncWebhookAuthenticate::tokenCacheKey($token), true, now()->addSeconds($ttl));

        return response($token, 200)->header('Content-Type', 'text/plain');
    }

    public function notify(Request $request): Response|JsonResponse
    {
        $payload = $request->all();

        if ($this->isPingPayload($payload)) {
            return response()->json(['status' => 'OK', 'message' => 'ping']);
        }

        try {
            $hash = BncNotification::hashFor($payload);
            $existing = BncNotification::where('hash', $hash)->first();

            if ($existing) {
                return response()->json(['status' => 'OK', 'message' => 'duplicado']);
            }

            $notification = BncNotification::create([
                'hash' => $hash,
                'payment_type' => $this->first($payload, ['PaymentType', 'paymentType']),
                'origin_bank_reference' => $this->first($payload, ['OriginBankReference', 'originBankReference']),
                'destiny_bank_reference' => $this->first($payload, ['DestinyBankReference', 'destinyBankReference']),
                'origin_bank_code' => $this->first($payload, ['OriginBankCode', 'originBankCode']),
                'amount' => $this->amount($this->first($payload, ['Amount', 'amount'])),
                'currency_code' => $this->first($payload, ['CurrencyCode', 'currencyCode']),
                'transaction_date' => $this->date($this->first($payload, ['Date', 'TxDate', 'date', 'txDate'])),
                'transaction_hour' => $this->first($payload, ['Hour', 'TxHour', 'hour', 'txHour']),
                'commerce_id' => $this->first($payload, ['CommerceID', 'commerceId']),
                'client_id' => $this->first($payload, ['ClientID', 'clientId']),
                'client_phone' => $this->first($payload, ['ClientPhone', 'clientPhone']),
                'debtor_account' => $this->first($payload, ['DebtorAccount', 'debtorAccount']),
                'debtor_id' => $this->first($payload, ['DebtorID', 'debtorId']),
                'creditor_account' => $this->first($payload, ['CreditorAccount', 'creditorAccount']),
                'concept' => $this->first($payload, ['Concept', 'concept']),
                'raw_payload' => $payload,
                'status' => BncNotification::STATUS_RECEIVED,
            ]);

            ProcessBncNotification::dispatch($notification->id)->afterResponse();

            return response()->json(['status' => 'OK', 'message' => 'recibido']);
        } catch (\Throwable $e) {
            Log::error('Error recibiendo notificación BNC: '.$e->getMessage(), [
                'payload' => $this->safePayload($payload),
            ]);

            return response()->json(['status' => 'KO', 'message' => 'No se pudo almacenar la notificación.'], 500);
        }
    }

    private function isPingPayload(array $payload): bool
    {
        if ($payload === []) {
            return true;
        }

        $keys = array_map('strtolower', array_keys($payload));
        $notificationKeys = [
            'paymenttype',
            'originbankreference',
            'destinybankreference',
            'amount',
            'commerceid',
            'creditoraccount',
        ];

        return $keys === ['ping'] || count(array_intersect($keys, $notificationKeys)) === 0;
    }

    private function first(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload) && filled($payload[$key])) {
                return trim((string) $payload[$key]);
            }
        }

        return null;
    }

    private function amount(?string $amount): float
    {
        if ($amount === null) {
            return 0.0;
        }

        $normalized = preg_replace('/[^0-9,.\-]/', '', $amount);

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace(',', '', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return round((float) $normalized, 2);
    }

    private function date(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        foreach (['Ymd', 'Y-m-d', 'd/m/Y', 'Y/m/d', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);

                if ($date !== false) {
                    return $date->toDateString();
                }
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function safePayload(array $payload): array
    {
        unset($payload['DebtorAccount'], $payload['CreditorAccount'], $payload['ClientPhone'], $payload['DebtorID']);

        return $payload;
    }
}
