<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'student_id',
        'payment_concept_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function paymentConcept()
    {
        return $this->belongsTo(PaymentConcept::class, 'payment_concept_id');
    }
}
