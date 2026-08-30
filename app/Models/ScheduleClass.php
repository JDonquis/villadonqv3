<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_id',
        'day',
        'start_time',
        'end_time',
        'matter_id',
        'teacher_id',
        'order',
    ];

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function matter()
    {
        return $this->belongsTo(Matter::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
