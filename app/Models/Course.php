<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    // public function quota()
    // {
    // return $this->hasMany(Quota::class,'course_id','id');
    // }
    public function section()
    {
        return $this->belongsToMany(Section::class, 'course_sections');
    }

    public function matters()
    {
        return $this->belongsToMany(Matter::class, 'courses_matters', 'course_id', 'matter_id');
    }
}
