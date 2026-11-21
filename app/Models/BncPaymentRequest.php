<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BncPaymentRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'user_id',
        'account_payment_id',
        'type',
        'reference',
        'reference_normalized',
        'amount',
        'amount_usd',
        'amount_ves',
        'currency',
        'transaction_date',
        'reported_date',
        'payload',
        'allowed_student_ids',
        'status',
        'attempts',
        'last_error',
        'last_checked_at',
        'verified_at',
        'payment_id',
        'payment_ids',
        'verification_source',
        'verification_response',
    ];

    protected $casts = [
        'payload' => 'array',
        'allowed_student_ids' => 'array',
        'payment_ids' => 'array',
        'verification_response' => 'array',
        'amount' => 'decimal:2',
        'amount_usd' => 'decimal:2',
        'amount_ves' => 'decimal:2',
        'transaction_date' => 'date',
        'reported_date' => 'date',
        'last_checked_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function accountPayment()
    {
        return $this->belongsTo(AccountPayment::class, 'account_payment_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function notifications()
    {
        return $this->hasMany(BncNotification::class);
    }

    public function studentIds(): array
    {
        $ids = $this->allowed_student_ids;

        if (is_array($ids) && $ids !== []) {
            return array_map('intval', $ids);
        }

        $payload = $this->payload ?? [];

        if (($this->type ?? 'regular') === 'charges') {
            return collect($payload['items'] ?? [])
                ->pluck('student_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return collect($payload['students'] ?? [])
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_VERIFIED => 'Verificado',
            self::STATUS_REJECTED => 'Rechazado',
            self::STATUS_EXPIRED => 'Expirado',
            default => 'Error',
        };
    }
}
