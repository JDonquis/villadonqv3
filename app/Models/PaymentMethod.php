<?php

namespace App\Models;

use App\Models\AccountPayment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function accounts()
    {
        return $this->hasMany(AccountPayment::class, 'payment_method_id', 'id');
    }


}
