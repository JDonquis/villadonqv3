<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BalancePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'balance_student_id',
        'amount',
        'month',
        'is_inscription',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function balanceStudent()
    {
        return $this->belongsTo(BalanceStudent::class);
    }
}
