<?php

namespace App\Models;

use App\Models\Section;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
        return $this->belongsToMany(Section::class,'course_sections');
    }


           
}

