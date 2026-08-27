<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lapse extends Model
{
    use HasFactory;

    protected $table = 'lapses';

    protected $fillable = [
        'start',
        'end',
        'number',
        'school_lapse_id',
    ];

    public $timestamps = false;

    public function schoolLapse()
    {
        return $this->belongsTo(SchoolLapse::class);
    }

    public function evaluationPlans()
    {
        return $this->hasMany(EvaluationPlan::class);
    }
}
