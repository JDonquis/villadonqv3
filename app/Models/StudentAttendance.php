<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentAttendance extends Model
{
    use HasFactory;

    protected $table = 'student_attendances';

    protected $fillable = [
        'session_id',
        'student_id',
        'status',
        'notified_at',
    ];

    protected $casts = [
        'status' => 'string',
        'notified_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(EvaluationPlanAttendanceSession::class, 'session_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
