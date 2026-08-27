<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvaluationPlanItem extends Model
{
    use HasFactory;

    protected $table = 'evaluation_plan_items';

    protected $fillable = [
        'evaluation_plan_id',
        'name',
        'percentage',
        'date',
        'order',
    ];

    public $timestamps = false;

    public function plan()
    {
        return $this->belongsTo(EvaluationPlan::class, 'evaluation_plan_id');
    }

    public function grades()
    {
        return $this->hasMany(StudentGrade::class, 'plan_item_id');
    }
}
