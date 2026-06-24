<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MainConfig extends Model
{
    use HasFactory;

    protected $table = 'main_configs';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'rif',
        'phone_number',
        'address',
        'email',
        'release',
        'motto',
        'regular_inscription_price',
        'new_inscription_price',
        'monthly_payment',
        'ame_price',
        'investment_plan_price',
        'day_of_monthly_payment',
        'grace_period',
    ];
}
