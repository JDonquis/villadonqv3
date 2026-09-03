<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentGradePublicationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'publication_id',
        'plan_item_id',
        'student_id',
        'score',
    ];

    public function publication()
    {
        return $this->belongsTo(StudentGradePublication::class, 'publication_id');
    }

    public function planItem()
    {
        return $this->belongsTo(EvaluationPlanItem::class, 'plan_item_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}