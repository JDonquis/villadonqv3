<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GradeSheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_lapse_id',
        'course_id',
        'name',
        'created_by',
    ];

    public function schoolLapse()
    {
        return $this->belongsTo(SchoolLapse::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function adjustments()
    {
        return $this->hasMany(GradeSheetAdjustment::class);
    }
}
