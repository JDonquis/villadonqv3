<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolEvent extends Model
{
    use HasFactory;

    public const TYPES = ['general', 'exam', 'meeting', 'holiday'];

    protected $fillable = [
        'title',
        'description',
        'type',
        'start_date',
        'end_date',
        'course_id',
        'section_id',
        'created_by',
        'published_at',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'published_at' => 'datetime',
        'status' => 'integer',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 1)->whereNotNull('published_at');
    }
}
