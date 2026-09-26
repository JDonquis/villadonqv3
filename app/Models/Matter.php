<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Matter extends Model
{
    use HasFactory;

    protected $table = 'matters';

    protected $fillable = [
        'name',
        'literary_grading_secondary',
    ];

    protected $casts = [
        'literary_grading_secondary' => 'boolean',
    ];

    public $timestamps = false;

    public function teachers()
    {
        return $this->belongsToMany(User::class, 'teacher_matter', 'matter_id', 'teacher_id');
    }

    public function evaluationPlans()
    {
        return $this->hasMany(EvaluationPlan::class);
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'courses_matters', 'matter_id', 'course_id');
    }
}
