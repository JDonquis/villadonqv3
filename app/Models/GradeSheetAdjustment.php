<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GradeSheetAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'grade_sheet_id',
        'student_id',
        'matter_id',
        'points',
    ];

    protected $casts = [
        'points' => 'integer',
    ];

    public function gradeSheet()
    {
        return $this->belongsTo(GradeSheet::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function matter()
    {
        return $this->belongsTo(Matter::class);
    }
}
