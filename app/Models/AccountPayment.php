<?php

namespace App\Models;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AccountPayment extends Model
{
    use HasFactory;

    protected $fillable =
    [
        'payment_method_id',
        'person_name',
        'email',
        'ci',
        'phone_number',
        'bank',
        'account_number',
        'username',
        'email',
        'status',
        'cash_currency'
    ];


    public function method()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id', 'id');
    }
}
