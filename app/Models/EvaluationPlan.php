<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvaluationPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'matter_id',
        'school_lapse_id',
        'lapse_id',
        'course_id',
        'section_id',
        'name',
        'description',
        'status',
        'admin_note',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function matter()
    {
        return $this->belongsTo(Matter::class);
    }

    public function schoolLapse()
    {
        return $this->belongsTo(SchoolLapse::class);
    }

    public function lapse()
    {
        return $this->belongsTo(Lapse::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function items()
    {
        return $this->hasMany(EvaluationPlanItem::class)->orderBy('order');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
