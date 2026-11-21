<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BncNotification extends Model
{
    use HasFactory;

    public const STATUS_RECEIVED = 'received';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_UNMATCHED = 'unmatched';

    public const STATUS_DUPLICATE = 'duplicate';

    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'hash',
        'payment_type',
        'origin_bank_reference',
        'destiny_bank_reference',
        'origin_bank_code',
        'amount',
        'currency_code',
        'transaction_date',
        'transaction_hour',
        'commerce_id',
        'client_id',
        'client_phone',
        'debtor_account',
        'debtor_id',
        'creditor_account',
        'concept',
        'raw_payload',
        'status',
        'bnc_payment_request_id',
        'payment_id',
        'error',
        'processed_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
        'processed_at' => 'datetime',
    ];

    public function paymentRequest()
    {
        return $this->belongsTo(BncPaymentRequest::class, 'bnc_payment_request_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public static function hashFor(array $payload): string
    {
        return hash('sha256', json_encode(self::canonicalize($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function canonicalize(array $payload): array
    {
        ksort($payload);

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::canonicalize($value);
            }
        }

        return $payload;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_RECEIVED => 'Recibida',
            self::STATUS_PROCESSING => 'Procesando',
            self::STATUS_PROCESSED => 'Procesada',
            self::STATUS_UNMATCHED => 'Sin conciliar',
            self::STATUS_DUPLICATE => 'Duplicada',
            default => 'Error',
        };
    }
}
