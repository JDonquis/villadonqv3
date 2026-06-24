<?php

namespace App\Models;

use App\Models\Course;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Section extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public $timestamps = false;
    // public function quota()
    // {
    // return $this->hasMany(Quota::class,'course_id','id');
    // }
    
    public function course()
    {
        return $this->belongsToMany(Course::class,'course_sections');
    }
}

