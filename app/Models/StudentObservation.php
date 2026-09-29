<?php

namespace App\Models;

use App\Enums\StudentObservationTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentObservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'evaluation_plan_id',
        'student_id',
        'created_by',
        'type',
        'body',
        'shared_with_representative',
        'shared_at',
    ];

    protected $casts = [
        'type' => StudentObservationTypeEnum::class,
        'shared_with_representative' => 'boolean',
        'shared_at' => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(EvaluationPlan::class, 'evaluation_plan_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isShared(): bool
    {
        return (bool) $this->shared_with_representative;
    }
}
