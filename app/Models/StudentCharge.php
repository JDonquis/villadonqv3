<?php

namespace App\Models;

use App\Enums\BalanceStudentStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentCharge extends Model
{
    use HasFactory;

    public const TYPE_AME = 'ame';

    public const TYPE_INVESTMENT_PLAN = 'investment_plan';

    protected $fillable = [
        'student_id',
        'school_lapse_id',
        'payment_concept_id',
        'type',
        'amount',
        'paid_amount',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'status' => BalanceStudentStatusEnum::class,
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolLapse()
    {
        return $this->belongsTo(SchoolLapse::class);
    }

    public function paymentConcept()
    {
        return $this->belongsTo(PaymentConcept::class, 'payment_concept_id');
    }

    public function studentChargePayments()
    {
        return $this->hasMany(StudentChargePayment::class);
    }

    public function remaining(): float
    {
        return max(0, round((float) $this->amount - (float) $this->paid_amount, 2));
    }

    public function isPaid(): bool
    {
        return $this->remaining() <= 0;
    }

    /**
     * Recalcula paid_amount a partir de sus abonos y actualiza el status.
     */
    public function syncFromPayments(): void
    {
        $paid = (float) $this->studentChargePayments()->sum('amount');
        $amount = (float) $this->amount;

        $this->paid_amount = round($paid, 2);

        $this->status = match (true) {
            $paid >= $amount && $amount >= 0 => BalanceStudentStatusEnum::Paid->value,
            $paid > 0 => BalanceStudentStatusEnum::PartiallyPaid->value,
            default => BalanceStudentStatusEnum::Pending->value,
        };

        $this->save();
    }
}
