<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolLapse extends Model
{
    use HasFactory;

    protected $table = 'school_lapses';

       protected $fillable = [
        'start',
        'end',
        'status',
    ];

    public $timestamps = false;
}
