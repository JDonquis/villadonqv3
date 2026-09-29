<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentGrade extends Model
{
    use HasFactory;

    protected $table = 'student_grades';

    protected $fillable = [
        'plan_item_id',
        'student_id',
        'score',
        'graded_by',
    ];

    public function planItem()
    {
        return $this->belongsTo(EvaluationPlanItem::class, 'plan_item_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Usuario que registró la nota (profesor o personal de administración).
     */
    public function gradedBy()
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
