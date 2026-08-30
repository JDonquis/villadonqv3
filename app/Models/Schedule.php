<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_lapse_id',
        'course_id',
        'section_id',
        'recess_start',
        'recess_duration_minutes',
    ];

    protected $casts = [
        'recess_start' => 'string',
    ];

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

    public function classes()
    {
        return $this->hasMany(ScheduleClass::class)->orderBy('day')->orderBy('start_time');
    }
}
