<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolCharge extends Model
{
    use HasFactory;

    public const AMOUNT = 1.00;

    protected $fillable = [
        'student_id',
        'school_lapse_id',
        'amount',
        'status',
        'student_status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolLapse()
    {
        return $this->belongsTo(SchoolLapse::class);
    }
}
