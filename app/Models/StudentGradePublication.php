<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentGradePublication extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_plan_id',
        'published_by',
        'version',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(EvaluationPlan::class, 'evaluation_plan_id');
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function items()
    {
        return $this->hasMany(StudentGradePublicationItem::class, 'publication_id');
    }

    public function rasgos()
    {
        return $this->hasMany(StudentGradePublicationRasgo::class, 'publication_id');
    }
}
