<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'account_payment_id',
        'date',
        'total_in_dolars',
        'total_in_bs',
        'reference',
        'status',
        'observations',
        'deleted_by',
        'reported_date',
    ];

    protected $casts = [
        'reported_date' => 'date',
        'date' => 'date',
        'total_in_dolars' => 'decimal:2',
        'total_in_bs' => 'decimal:2',
        'status' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function accountPayment()
    {
        return $this->belongsTo(AccountPayment::class, 'account_payment_id');
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'payment_students')
            ->withPivot('amount_in_dolars')
            ->withTimestamps();
    }

    public function histories()
    {
        return $this->hasMany(PaymentHistory::class);
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn($value) => ucfirst(Carbon::parse($value)->translatedFormat('D j F, Y')),
        );
    }

    protected function rawDate(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['date'] ?? null,
        );
    }
}
