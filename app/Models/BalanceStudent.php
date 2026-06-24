<?php

namespace App\Models;

use App\Enums\BalanceStudentStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BalanceStudent extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'status',
        'inscription',
        'inscription_status',
        'january',
        'january_status',
        'february',
        'february_status',
        'march',
        'march_status',
        'april',
        'april_status',
        'may',
        'may_status',
        'june',
        'june_status',
        'july',
        'july_status',
        'august',
        'august_status',
        'september',
        'september_status',
        'october',
        'october_status',
        'november',
        'november_status',
        'december',
        'december_status',
        'school_lapse_id',
    ];

    protected $casts = [
        'status' => BalanceStudentStatusEnum::class,
        'inscription_status' => BalanceStudentStatusEnum::class,
        'january_status' => BalanceStudentStatusEnum::class,
        'february_status' => BalanceStudentStatusEnum::class,
        'march_status' => BalanceStudentStatusEnum::class,
        'april_status' => BalanceStudentStatusEnum::class,
        'may_status' => BalanceStudentStatusEnum::class,
        'june_status' => BalanceStudentStatusEnum::class,
        'july_status' => BalanceStudentStatusEnum::class,
        'august_status' => BalanceStudentStatusEnum::class,
        'september_status' => BalanceStudentStatusEnum::class,
        'october_status' => BalanceStudentStatusEnum::class,
        'november_status' => BalanceStudentStatusEnum::class,
        'december_status' => BalanceStudentStatusEnum::class,
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
}
