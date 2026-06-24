<?php

namespace App\Models;

use App\Models\SchoolLapse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Quota extends Model
{
    use HasFactory;

    protected $fillable = [
        'assigned',
        'accepted',
        'remaining',
        'course_id',
        'school_lapse_id',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class,'course_id');
    }

    public function school_lapse()
    {
        return $this->belongsTo(SchoolLapse::class);
    }
}
