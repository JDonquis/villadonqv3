<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvaluationPlanAttendanceSession extends Model
{
    use HasFactory;

    protected $table = 'evaluation_plan_attendance_sessions';

    protected $fillable = [
        'evaluation_plan_id',
        'date',
        'order',
    ];

    protected $casts = [
        'date' => 'date',
        'order' => 'integer',
    ];

    public function plan()
    {
        return $this->belongsTo(EvaluationPlan::class, 'evaluation_plan_id');
    }

    public function attendances()
    {
        return $this->hasMany(StudentAttendance::class, 'session_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('date');
    }
}