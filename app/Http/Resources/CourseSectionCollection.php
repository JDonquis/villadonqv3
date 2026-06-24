<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CourseSectionCollection extends ResourceCollection
{

    public function toArray(Request $request)
    {
        $response = [];
        foreach ($this as $courseSection) 
        {   
            $response['course_'.$courseSection->course_id][] = ['id' => $courseSection->section_id, 'name' => $courseSection->section->name];
        }



        return $response;
    }

}
