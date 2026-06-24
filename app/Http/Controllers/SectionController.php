<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\Request;
use App\Models\CourseSection;
use App\Services\StudentService;
use Illuminate\Support\Facades\DB;

class SectionController extends Controller
{

     /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {   
        $nextSectionId = $request->section_id + 1;

        CourseSection::create(['course_id' => $request->course_id, 'section_id' => $nextSectionId])->save(); 

        return redirect('/dashboard/matricula?course_id='.$request->course_id.'&section_id='.$nextSectionId);
    }

    public function destroy($course_id, $section_id)
    {      
        $previousSectionId = $section_id - 1;
        Student::where('course_id',$course_id)->where('section_id',$section_id)->update(['section_id' => $previousSectionId]);
              
        CourseSection::where('course_id',$course_id)->where('section_id',$section_id)->delete(); 

        return redirect('/dashboard/matricula?course_id='.$course_id.'&section_id='.$previousSectionId);

    }
}
