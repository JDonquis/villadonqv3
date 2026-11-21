<?php

namespace App\Services\Bnc;

use App\Exceptions\BncException;
use App\Models\AccountPayment;
use App\Models\BncNotification;
use App\Models\BncPaymentRequest;
use App\Models\Payment;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BncPaymentVerifier
{
    public function __construct(
        private BncClient $client = new BncClient,
        private PaymentService $paymentService = new PaymentService
    ) {}

    public function isEligible(AccountPayment $account): bool
    {
        return BncSupport::accountIsEligible($account);
    }

    public function resolveAccount(AccountPayment $account): array
    {
        return [
            'account_number' => BncSupport::resolveAccountNumber($account),
            'client_id' => BncSupport::resolveClientId(),
            'currency' => BncSupport::normalizeCurrency(config('services.bnc.currency', 'VES')),
            'bank_code' => (int) config('services.bnc.default_payer_bank_code', 191),
            'mode' => (string) config('services.bnc.verification_mode', 'auto'),
        ];
    }

    public function verify(BncPaymentRequest $request, bool $force = false): BncPaymentRequest
    {
        return DB::transaction(function () use ($request, $force) {
            $request = BncPaymentRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($request->status === BncPaymentRequest::STATUS_VERIFIED) {
                return $request;
            }

            if (! $force && in_array($request->status, [BncPaymentRequest::STATUS_REJECTED, BncPaymentRequest::STATUS_EXPIRED], true)) {
                return $request;
            }

            $account = AccountPayment::with('method')->find($request->account_payment_id);

            if (! $account) {
                return $this->rememberError($request, 'La cuenta de pago asociada ya no existe.');
            }

            if (! $this->isEligible($account)) {
                return $this->rememberError($request, 'La cuenta seleccionada no está habilitada para verificación BNC.');
            }

            $config = $this->resolveAccount($account);

            if (! $config['account_number'] || ! $config['client_id']) {
                return $this->rememberError($request, 'Faltan BNC_ACCOUNT_NUMBER o BNC_CLIENT_ID para verificar la operación.');
            }

            $reference = BncSupport::normalizeReference($request->reference);

            if (! $reference && config('services.bnc.require_reference', true)) {
                return $this->rememberError($request, 'La referencia bancaria es obligatoria para verificar el pago.');
            }

            $amount = BncSupport::amountForCurrency(
                (float) $request->amount_usd,
                (float) $request->amount_ves,
                $config['currency']
            );

            $lastError = null;
            $movement = null;

            if ($reference) {
                try {
                    $response = $this->client->validate([
                        'ClientID' => $config['client_id'],
                        'AccountNumber' => $config['account_number'],
                        'Reference' => $reference,
                        'Amount' => $amount,
                        'DateMovement' => BncSupport::dateForApi($request->transaction_date),
                        'ChildClientID' => '',
                        'BranchID' => '',
                    ]);

                    $data = $response['data'] ?? [];

                    if (filter_var($data['MovementExists'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                        $movement = $data;
                    }
                } catch (BncException $e) {
                    $lastError = $e->getMessage();
                } catch (Throwable $e) {
                    $lastError = 'Error validando la referencia: '.$e->getMessage();
                }
            }

            if (! $movement && $reference) {
                $movement = $this->searchHistory($config, $request, $reference, $amount, $lastError);
            }

            if (! $movement) {
                if ($lastError === null) {
                    $lastError = 'El movimiento no aparece todavía en el BNC.';
                }

                $request->attempts++;
                $request->last_checked_at = now();
                $request->last_error = $lastError;
                $request->status = BncPaymentRequest::STATUS_PENDING;

                if ($request->created_at && $request->created_at->lt(now()->subDays(max(1, (int) config('services.bnc.request_ttl_days', 7))))) {
                    $request->status = BncPaymentRequest::STATUS_EXPIRED;
                    $request->last_error = 'Tiempo de verificación agotado. '.$lastError;
                }

                $request->save();

                return $request;
            }

            $movementAmount = BncSupport::movementAmount($movement);

            if (! BncSupport::amountsMatch($amount, $movementAmount)) {
                return $this->reject(
                    $request,
                    sprintf(
                        'El monto del movimiento BNC (%s) no coincide con el monto registrado (%s).',
                        number_format($movementAmount, 2, '.', ''),
                        number_format($amount, 2, '.', '')
                    ),
                    $movement
                );
            }

            $this->applyPayment($request, $movement, 'bnc_api');

            return $request->refresh();
        });
    }

    public function verifyManually(BncPaymentRequest $request, ?string $note = null): BncPaymentRequest
    {
        return DB::transaction(function () use ($request, $note) {
            $request = BncPaymentRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($request->status === BncPaymentRequest::STATUS_VERIFIED) {
                return $request;
            }

            $movement = [
                'MovementExists' => true,
                'ControlNumber' => 'MANUAL-'.$request->id.'-'.now()->format('YmdHis'),
                'Amount' => (float) $request->amount,
                'Date' => $request->transaction_date?->format('d/m/Y'),
                'Concept' => $note ?: 'Verificación manual desde administración',
                'ReferenceA' => $request->reference,
            ];

            $this->applyPayment($request, $movement, 'manual');

            return $request->refresh();
        });
    }

    public function verifyFromNotification(BncNotification $notification): BncNotification
    {
        return DB::transaction(function () use ($notification) {
            $notification = BncNotification::whereKey($notification->id)->lockForUpdate()->firstOrFail();

            if (in_array($notification->status, [BncNotification::STATUS_PROCESSED, BncNotification::STATUS_DUPLICATE], true)) {
                return $notification;
            }

            $request = $this->matchRequest($notification);

            if (! $request) {
                $notification->status = BncNotification::STATUS_UNMATCHED;
                $notification->error = 'No se encontró una solicitud pendiente que coincida con la referencia y el monto.';
                $notification->processed_at = now();
                $notification->save();

                $this->notifyUnmatched($notification);

                return $notification;
            }

            $request = BncPaymentRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($request->status === BncPaymentRequest::STATUS_VERIFIED) {
                $notification->status = BncNotification::STATUS_DUPLICATE;
                $notification->bnc_payment_request_id = $request->id;
                $notification->payment_id = $request->payment_id;
                $notification->processed_at = now();
                $notification->save();

                return $notification;
            }

            $amount = $this->notificationExpectedAmount($notification, $request);

            if (! BncSupport::amountsMatch($amount, (float) $notification->amount)) {
                $this->reject($request, sprintf(
                    'El monto de la notificación BNC (%s) no coincide con el monto registrado (%s).',
                    number_format((float) $notification->amount, 2, '.', ''),
                    number_format($amount, 2, '.', '')
                ), $this->movementFromNotification($notification));

                $notification->status = BncNotification::STATUS_ERROR;
                $notification->error = 'Monto no coincide con la solicitud pendiente.';
                $notification->bnc_payment_request_id = $request->id;
                $notification->processed_at = now();
                $notification->save();

                return $notification;
            }

            $movement = $this->movementFromNotification($notification);

            $this->applyPayment($request, $movement, 'bnc_webhook');

            $notification->status = BncNotification::STATUS_PROCESSED;
            $notification->bnc_payment_request_id = $request->id;
            $notification->payment_id = $request->refresh()->payment_id;
            $notification->processed_at = now();
            $notification->error = null;
            $notification->save();

            return $notification;
        });
    }

    public function reject(BncPaymentRequest $request, string $reason, array $movement = []): BncPaymentRequest
    {
        $request->status = BncPaymentRequest::STATUS_REJECTED;
        $request->last_error = $reason;
        $request->last_checked_at = now();
        $request->attempts++;
        $request->verification_response = $movement !== [] ? $movement : $request->verification_response;
        $request->save();

        $this->notifyRejected($request, $reason);

        return $request;
    }

    private function applyPayment(BncPaymentRequest $request, array $movement, string $source): void
    {
        if ($request->payment_id) {
            $existing = Payment::find($request->payment_id);

            if ($existing) {
                $request->status = BncPaymentRequest::STATUS_VERIFIED;
                $request->verified_at = $request->verified_at ?? now();
                $request->verification_source = $source;
                $request->verification_response = $movement;
                $request->save();

                return;
            }
        }

        $payload = $request->payload ?? [];
        $allowed = $request->allowed_student_ids ?: $request->studentIds();

        $payments = [];

        if (($request->type ?? 'regular') === 'charges') {
            $payments = $this->paymentService->createForStudentCharges($payload, $allowed, (int) $request->user_id);
        } else {
            $payments = [$this->paymentService->create($payload, $allowed, (int) $request->user_id)];
        }

        $payments = array_values(array_filter($payments, fn ($payment) => $payment instanceof Payment));
        $paymentIds = array_map(fn (Payment $payment) => $payment->id, $payments);
        $primary = $payments[0] ?? null;
        $controlNumber = $movement['ControlNumber'] ?? $movement['DestinyBankReference'] ?? null;

        foreach ($payments as $payment) {
            $payment->update([
                'verification_status' => 'verified',
                'verification_source' => $source,
                'verified_at' => now(),
                'verified_by' => null,
                'verification_reference' => $controlNumber,
                'verification_response' => $movement,
            ]);
        }

        $request->status = BncPaymentRequest::STATUS_VERIFIED;
        $request->verified_at = now();
        $request->payment_id = $primary?->id;
        $request->payment_ids = $paymentIds;
        $request->verification_source = $source;
        $request->verification_response = $movement;
        $request->last_error = null;
        $request->last_checked_at = now();
        $request->attempts++;
        $request->save();

        if (Auth::id() !== (int) $request->user_id) {
            $this->notifyVerified($request, $payments);
        }
    }

    private function searchHistory(array $config, BncPaymentRequest $request, string $reference, float $amount, ?string &$lastError): ?array
    {
        $date = Carbon::parse($request->transaction_date);

        $attempts = [
            [$date->copy()->subDays(1)->toDateString().'T00:00:00', $date->copy()->addDays(1)->toDateString().'T23:59:59'],
            [$date->copy()->subDays(3)->toDateString().'T00:00:00', $date->copy()->addDays(1)->toDateString().'T23:59:59'],
        ];

        foreach ($attempts as [$start, $end]) {
            try {
                $response = $this->client->historyByDate([
                    'ClientID' => $config['client_id'],
                    'AccountNumber' => $config['account_number'],
                    'StartDate' => $start,
                    'EndDate' => $end,
                    'ChildClientID' => '',
                    'BranchID' => '',
                ]);

                $movement = BncSupport::findMovement($response['data'] ?? [], $reference, $amount, $request->transaction_date);

                if ($movement) {
                    return $movement;
                }
            } catch (BncException $e) {
                $lastError = $e->getMessage();
            } catch (Throwable $e) {
                $lastError = 'Error consultando historial BNC: '.$e->getMessage();
            }
        }

        return null;
    }

    private function matchRequest(BncNotification $notification): ?BncPaymentRequest
    {
        $references = BncSupport::referenceCandidates($notification->origin_bank_reference);

        if ($references === []) {
            $references = BncSupport::referenceCandidates($notification->destiny_bank_reference);
        }

        if ($references === []) {
            return null;
        }

        $query = BncPaymentRequest::with('accountPayment')
            ->whereIn('status', [BncPaymentRequest::STATUS_PENDING, BncPaymentRequest::STATUS_ERROR])
            ->where(function ($query) use ($references) {
                $query->whereIn('reference', $references)
                    ->orWhereIn('reference_normalized', $references);
            })
            ->when($notification->transaction_date, function ($query) use ($notification) {
                $query->whereDate('transaction_date', '>=', $notification->transaction_date->copy()->subDays(3))
                    ->whereDate('transaction_date', '<=', $notification->transaction_date->copy()->addDays(1));
            })
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        $matches = [];

        foreach ($query as $request) {
            if (! $this->referenceMatchesNotification($request, $notification)) {
                continue;
            }

            $expected = $this->notificationExpectedAmount($notification, $request);

            if (! BncSupport::amountsMatch($expected, (float) $notification->amount)) {
                continue;
            }

            if ($notification->transaction_date && ! BncSupport::datesClose($request->transaction_date, $notification->transaction_date, 2)) {
                continue;
            }

            $account = $request->accountPayment;

            if ($account) {
                $config = $this->resolveAccount($account);

                if ($notification->creditor_account && $config['account_number']
                    && BncSupport::normalizeAccount($notification->creditor_account) !== $config['account_number']) {
                    continue;
                }

                if ($notification->commerce_id && $config['client_id']) {
                    $commerce = BncSupport::normalizeRif($notification->commerce_id);

                    if ($commerce && $commerce !== $config['client_id']) {
                        continue;
                    }
                }
            }

            $matches[] = $request;
        }

        if ($matches === []) {
            return null;
        }

        usort($matches, function (BncPaymentRequest $a, BncPaymentRequest $b) use ($notification) {
            $aExact = $a->transaction_date?->toDateString() === $notification->transaction_date?->toDateString() ? 0 : 1;
            $bExact = $b->transaction_date?->toDateString() === $notification->transaction_date?->toDateString() ? 0 : 1;

            return $aExact <=> $bExact ?: $a->id <=> $b->id;
        });

        return $matches[0];
    }

    private function referenceMatchesNotification(BncPaymentRequest $request, BncNotification $notification): bool
    {
        $candidates = array_merge(
            BncSupport::referenceCandidates($notification->origin_bank_reference),
            BncSupport::referenceCandidates($notification->destiny_bank_reference)
        );

        foreach ($candidates as $candidate) {
            if (BncSupport::referencesMatch($request->reference, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function notificationExpectedAmount(BncNotification $notification, BncPaymentRequest $request): float
    {
        $currency = BncSupport::normalizeCurrency($notification->currency_code);

        if ($currency === 'USD') {
            return round((float) $request->amount_usd, 2);
        }

        if ($currency === 'VES') {
            return round((float) $request->amount_ves, 2);
        }

        return round((float) $request->amount, 2);
    }

    private function movementFromNotification(BncNotification $notification): array
    {
        return [
            'MovementExists' => true,
            'Amount' => (float) $notification->amount,
            'Date' => $notification->transaction_date?->format('d/m/Y'),
            'ControlNumber' => $notification->destiny_bank_reference,
            'ReferenceA' => $notification->origin_bank_reference,
            'ReferenceB' => $notification->client_phone,
            'ReferenceC' => $notification->debtor_account,
            'ReferenceD' => $notification->debtor_id,
            'BankCode' => $notification->origin_bank_code,
            'Type' => $notification->payment_type,
            'Concept' => $notification->concept,
            'CurrencyCode' => $notification->currency_code,
        ];
    }

    private function rememberError(BncPaymentRequest $request, string $message): BncPaymentRequest
    {
        $request->status = BncPaymentRequest::STATUS_ERROR;
        $request->last_error = $message;
        $request->last_checked_at = now();
        $request->attempts++;
        $request->save();

        return $request;
    }

    private function notifyVerified(BncPaymentRequest $request, array $payments): void
    {
        try {
            $service = app(NotificationService::class);
            $rep = User::find($request->user_id);
            $reference = $request->reference ?? '—';
            $amount = number_format((float) $request->amount_usd, 2, ',', '.');
            $body = "El pago con referencia {$reference} por \${$amount} fue verificado con el BNC.";

            if ($rep) {
                $service->send($rep, [
                    'type' => 'bnc_payment_verified',
                    'title' => 'Pago verificado',
                    'body' => $body,
                    'icon' => 'mdi:bank-check',
                    'severity' => 'success',
                    'meta' => ['payment_request_id' => $request->id],
                ], '/dashboard/mis-pagos');
            }

            $service->sendToUsers($service->admins('pagos'), [
                'type' => 'bnc_payment_verified',
                'title' => 'Pago BNC verificado',
                'body' => $body,
                'icon' => 'mdi:bank-check',
                'severity' => 'success',
                'meta' => ['payment_request_id' => $request->id],
            ], '/dashboard/pagos/verificaciones-bnc');
        } catch (Throwable $e) {
            Log::error('Error notificando pago BNC verificado '.$request->id.': '.$e->getMessage());
        }
    }

    private function notifyRejected(BncPaymentRequest $request, string $reason): void
    {
        try {
            $service = app(NotificationService::class);
            $rep = User::find($request->user_id);
            $body = 'No se pudo verificar el pago con referencia '.($request->reference ?? '—').': '.$reason;

            if ($rep) {
                $service->send($rep, [
                    'type' => 'bnc_payment_rejected',
                    'title' => 'Pago BNC rechazado',
                    'body' => $body,
                    'icon' => 'mdi:bank-remove',
                    'severity' => 'error',
                    'meta' => ['payment_request_id' => $request->id],
                ], '/dashboard/mis-pagos');
            }

            $service->sendToUsers($service->admins('pagos'), [
                'type' => 'bnc_payment_rejected',
                'title' => 'Pago BNC rechazado',
                'body' => $body,
                'icon' => 'mdi:bank-remove',
                'severity' => 'error',
                'meta' => ['payment_request_id' => $request->id],
            ], '/dashboard/pagos/verificaciones-bnc');
        } catch (Throwable $e) {
            Log::error('Error notificando rechazo BNC '.$request->id.': '.$e->getMessage());
        }
    }

    private function notifyUnmatched(BncNotification $notification): void
    {
        try {
            $service = app(NotificationService::class);
            $reference = $notification->origin_bank_reference ?? $notification->destiny_bank_reference ?? '—';
            $amount = number_format((float) $notification->amount, 2, ',', '.');

            $service->sendToUsers($service->admins('pagos'), [
                'type' => 'bnc_notification_unmatched',
                'title' => 'Notificación BNC sin conciliar',
                'body' => "Llegó un pago BNC por \${$amount} con referencia {$reference} que no coincide con ninguna solicitud pendiente.",
                'icon' => 'mdi:bank-alert',
                'severity' => 'warning',
                'meta' => ['bnc_notification_id' => $notification->id],
            ], '/dashboard/pagos/verificaciones-bnc');
        } catch (Throwable $e) {
            Log::error('Error notificando notificación BNC sin conciliar '.$notification->id.': '.$e->getMessage());
        }
    }
}
