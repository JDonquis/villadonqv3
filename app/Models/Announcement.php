<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    public const AUDIENCES = ['all', 'course', 'section', 'student'];

    protected $fillable = [
        'title',
        'body',
        'audience',
        'target_user_types',
        'course_id',
        'section_id',
        'student_id',
        'created_by',
        'published_at',
        'status',
    ];

    protected $casts = [
        'target_user_types' => 'array',
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

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function scopePublished($query)
    {
        return $query->where('status', 1)->whereNotNull('published_at');
    }
}
