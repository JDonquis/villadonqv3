<?php

namespace App\Services\Bnc;

use App\Models\AccountPayment;
use App\Models\BncPaymentRequest;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BncPaymentRequestService
{
    public function __construct(
        private BncPaymentVerifier $verifier = new BncPaymentVerifier
    ) {}

    public function create(
        array $data,
        AccountPayment $account,
        User $user,
        array $allowedStudentIds,
        string $type = 'regular'
    ): BncPaymentRequest {
        $config = $this->verifier->resolveAccount($account);
        $rawReference = trim((string) ($data['reference'] ?? ''));
        $reference = BncSupport::normalizeReference($rawReference);

        if (! $reference && config('services.bnc.require_reference', true)) {
            throw ValidationException::withMessages([
                'reference' => 'La referencia bancaria es obligatoria para verificar pagos con el BNC.',
            ]);
        }

        if ($rawReference !== '' && ! preg_match('/^\d+$/', $rawReference)) {
            throw ValidationException::withMessages([
                'reference' => 'La referencia bancaria debe contener solo números.',
            ]);
        }

        [$amountUsd, $amountVes] = $this->amounts($data, $type);
        $amount = BncSupport::amountForCurrency($amountUsd, $amountVes, $config['currency']);
        $transactionDate = BncSupport::dateOnly($data['date'] ?? now());

        if (! $transactionDate) {
            throw ValidationException::withMessages([
                'date' => 'La fecha del pago no es válida.',
            ]);
        }

        if (! $config['account_number'] || ! $config['client_id']) {
            throw ValidationException::withMessages([
                'account_payment_id' => 'La cuenta no tiene configurado el número BNC o el ClientID. Revisa las variables BNC_ACCOUNT_NUMBER y BNC_CLIENT_ID.',
            ]);
        }

        if ($reference) {
            $existing = BncPaymentRequest::where('account_payment_id', $account->id)
                ->where(function ($query) use ($reference) {
                    $query->where('reference', $reference)
                        ->orWhere('reference_normalized', $reference);
                })
                ->whereIn('status', [
                    BncPaymentRequest::STATUS_PENDING,
                    BncPaymentRequest::STATUS_ERROR,
                    BncPaymentRequest::STATUS_VERIFIED,
                ])
                ->latest('id')
                ->first();

            if ($existing) {
                if ($existing->status === BncPaymentRequest::STATUS_VERIFIED) {
                    throw ValidationException::withMessages([
                        'reference' => 'Esta referencia ya fue verificada y registrada anteriormente.',
                    ]);
                }

                if ((int) $existing->user_id === (int) $user->id) {
                    return $existing;
                }

                throw ValidationException::withMessages([
                    'reference' => 'Esta referencia ya está en proceso de verificación por otro usuario.',
                ]);
            }

            $paymentExists = Payment::where('account_payment_id', $account->id)
                ->where('reference', $reference)
                ->where('status', '!=', 0)
                ->exists();

            if ($paymentExists) {
                throw ValidationException::withMessages([
                    'reference' => 'Ya existe un pago registrado con esta referencia.',
                ]);
            }
        }

        return BncPaymentRequest::create([
            'user_id' => $user->id,
            'account_payment_id' => $account->id,
            'type' => $type,
            'reference' => $data['reference'] ?? $reference,
            'reference_normalized' => $reference,
            'amount' => $amount,
            'amount_usd' => $amountUsd,
            'amount_ves' => $amountVes,
            'currency' => $config['currency'],
            'transaction_date' => $transactionDate,
            'reported_date' => BncSupport::dateOnly($data['reported_date'] ?? null),
            'payload' => $data,
            'allowed_student_ids' => array_values(array_map('intval', $allowedStudentIds)),
            'status' => BncPaymentRequest::STATUS_PENDING,
        ]);
    }

    public function verify(BncPaymentRequest $request, bool $force = false): BncPaymentRequest
    {
        return $this->verifier->verify($request, $force);
    }

    public function verifyForUser(BncPaymentRequest $request, User $user): BncPaymentRequest
    {
        if ((int) $request->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'request' => 'Esta verificación no te pertenece.',
            ]);
        }

        return $this->verifier->verify($request);
    }

    public function reject(BncPaymentRequest $request, string $reason): BncPaymentRequest
    {
        return $this->verifier->reject($request, $reason);
    }

    public function pendingForUser(int $userId, int $limit = 20): Collection
    {
        return BncPaymentRequest::with('accountPayment.method')
            ->where('user_id', $userId)
            ->whereIn('status', [
                BncPaymentRequest::STATUS_PENDING,
                BncPaymentRequest::STATUS_ERROR,
                BncPaymentRequest::STATUS_REJECTED,
                BncPaymentRequest::STATUS_EXPIRED,
            ])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (BncPaymentRequest $request) => [
                'id' => $request->id,
                'reference' => $request->reference,
                'amount' => (float) $request->amount_usd,
                'amount_ves' => (float) $request->amount_ves,
                'currency' => $request->currency,
                'date' => $request->transaction_date?->toDateString(),
                'account' => $request->accountPayment?->method?->name,
                'bank' => $request->accountPayment?->bank,
                'status' => $request->status,
                'status_label' => $request->statusLabel(),
                'reference_normalized' => $request->reference_normalized,
                'last_error' => $request->last_error,
                'attempts' => $request->attempts,
                'created_at' => $request->created_at?->toIso8601String(),
            ]);
    }

    public function pendingCount(?int $userId = null): int
    {
        return BncPaymentRequest::query()
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->whereIn('status', [BncPaymentRequest::STATUS_PENDING, BncPaymentRequest::STATUS_ERROR])
            ->count();
    }

    public function expireOld(): int
    {
        $ttl = max(1, (int) config('services.bnc.request_ttl_days', 7));

        return BncPaymentRequest::whereIn('status', [
            BncPaymentRequest::STATUS_PENDING,
            BncPaymentRequest::STATUS_ERROR,
        ])
            ->where('created_at', '<', now()->subDays($ttl))
            ->update([
                'status' => BncPaymentRequest::STATUS_EXPIRED,
                'last_checked_at' => now(),
                'last_error' => 'Tiempo de verificación agotado.',
            ]);
    }

    public function payloadStudentIds(BncPaymentRequest $request): array
    {
        return $request->studentIds();
    }

    private function amounts(array $data, string $type): array
    {
        if ($type === 'charges') {
            $items = collect($data['items'] ?? []);

            return [
                round((float) $items->sum(fn ($item) => (float) ($item['amount_in_dolars'] ?? 0)), 2),
                round((float) $items->sum(fn ($item) => (float) ($item['amount_in_bs'] ?? 0)), 2),
            ];
        }

        return [
            round((float) ($data['total_in_dolars'] ?? 0), 2),
            round((float) ($data['total_in_bs'] ?? 0), 2),
        ];
    }
}
