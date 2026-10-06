<?php

namespace App\Models;

use App\Enums\BalanceStudentStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BalanceStudent extends Model
{
    use HasFactory;

    public const MONTHS = [
        'september',
        'october',
        'november',
        'december',
        'january',
        'february',
        'march',
        'april',
        'may',
        'june',
        'july',
        'august',
    ];

    protected $fillable = [
        'student_id',
        'status',
        'inscription',
        'inscription_status',
        'january',
        'january_status',
        'january_reminded',
        'february',
        'february_status',
        'february_reminded',
        'march',
        'march_status',
        'march_reminded',
        'april',
        'april_status',
        'april_reminded',
        'may',
        'may_status',
        'may_reminded',
        'june',
        'june_status',
        'june_reminded',
        'july',
        'july_status',
        'july_reminded',
        'august',
        'august_status',
        'august_reminded',
        'september',
        'september_status',
        'september_reminded',
        'october',
        'october_status',
        'october_reminded',
        'november',
        'november_status',
        'november_reminded',
        'december',
        'december_status',
        'december_reminded',
        'school_lapse_id',
    ];

    protected $casts = [
        'status' => BalanceStudentStatusEnum::class,
        'inscription_status' => BalanceStudentStatusEnum::class,
        'january_status' => BalanceStudentStatusEnum::class,
        'january_reminded' => 'boolean',
        'february_status' => BalanceStudentStatusEnum::class,
        'february_reminded' => 'boolean',
        'march_status' => BalanceStudentStatusEnum::class,
        'march_reminded' => 'boolean',
        'april_status' => BalanceStudentStatusEnum::class,
        'april_reminded' => 'boolean',
        'may_status' => BalanceStudentStatusEnum::class,
        'may_reminded' => 'boolean',
        'june_status' => BalanceStudentStatusEnum::class,
        'june_reminded' => 'boolean',
        'july_status' => BalanceStudentStatusEnum::class,
        'july_reminded' => 'boolean',
        'august_status' => BalanceStudentStatusEnum::class,
        'august_reminded' => 'boolean',
        'september_status' => BalanceStudentStatusEnum::class,
        'september_reminded' => 'boolean',
        'october_status' => BalanceStudentStatusEnum::class,
        'october_reminded' => 'boolean',
        'november_status' => BalanceStudentStatusEnum::class,
        'november_reminded' => 'boolean',
        'december_status' => BalanceStudentStatusEnum::class,
        'december_reminded' => 'boolean',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolLapse()
    {
        return $this->belongsTo(SchoolLapse::class);
    }

    public function balancePayments()
    {
        return $this->hasMany(BalancePayment::class);
    }

    /**
     * Deuda efectivamente vencida: inscripción + meses con status debt/partially_paid.
     * Los meses futuros (pending) no cuentan.
     */
    public function currentDebt(): float
    {
        $dueStatuses = [
            BalanceStudentStatusEnum::Debt->value,
            BalanceStudentStatusEnum::PartiallyPaid->value,
        ];

        $debt = 0.0;

        if ($this->inscription < 0) {
            $debt += abs((float) $this->inscription);
        }

        foreach (self::MONTHS as $month) {
            $status = $this->{$month.'_status'};
            $statusValue = $status instanceof BalanceStudentStatusEnum ? $status->value : $status;

            if ($this->$month < 0 && in_array($statusValue, $dueStatuses, true)) {
                $debt += abs((float) $this->$month);
            }
        }

        return $debt;
    }
}
