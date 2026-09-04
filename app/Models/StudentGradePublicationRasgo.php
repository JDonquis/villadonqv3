<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentGradePublicationRasgo extends Model
{
    use HasFactory;

    protected $table = 'student_grade_publication_rasgos';

    protected $fillable = [
        'publication_id',
        'student_id',
        'rasgos_score',
    ];

    public function publication()
    {
        return $this->belongsTo(StudentGradePublication::class, 'publication_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
