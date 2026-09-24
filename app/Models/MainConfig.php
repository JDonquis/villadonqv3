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
        'code',
        'municipality',
        'federal_entity',
        'cdcee',
        'director_name',
        'director_ci',
        'latitude',
        'longitude',
        'entity_shield',
        'regular_inscription_price',
        'new_inscription_price',
        'preescolar_inscription_price',
        'primaria_inscription_price',
        'secundaria_inscription_price',
        'monthly_payment',
        'ame_price',
        'investment_plan_price',
        'day_of_monthly_payment',
        'grace_period',
    ];
}
