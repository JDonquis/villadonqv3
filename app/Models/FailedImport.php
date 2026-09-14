<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FailedImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'row_number',
        'data',
        'error_message',
    ];

    protected $casts = [
        'data' => 'array',
    ];
}
