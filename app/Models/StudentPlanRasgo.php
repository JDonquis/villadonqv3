<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentPlanRasgo extends Model
{
    use HasFactory;

    protected $table = 'student_plan_rasgos';

    protected $fillable = [
        'evaluation_plan_id',
        'student_id',
        'rasgos_score',
    ];

    public function plan()
    {
        return $this->belongsTo(EvaluationPlan::class, 'evaluation_plan_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
