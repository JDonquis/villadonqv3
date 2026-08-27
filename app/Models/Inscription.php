<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inscription extends Model
{
    use HasFactory;

    protected $table = 'inscriptions';

    protected $fillable = [
        'school_lapse_id',
        'course_id',
        'section_id',
        'student_id',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolLapse()
    {
        return $this->belongsTo(SchoolLapse::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function documents()
    {
        return $this->hasMany(DocumentStudent::class);
    }
}
