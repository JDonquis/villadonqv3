<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentChargePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_charge_id',
        'payment_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function studentCharge()
    {
        return $this->belongsTo(StudentCharge::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
