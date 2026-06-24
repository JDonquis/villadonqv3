<?php  

namespace App\Services;

use DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Activity;

class BitacoraService
{	
	private User $userModel;


    public function __construct()
    {
        $this->userModel = new User;
    }

    public function getActivities($request)
    {
        $user = auth()->user();
        $activities = Activity::query()
        ->when($request->input('search'), function ($query, $search) 
        {
            $query->where('search','like','%' . $search . '%');
        })
        ->when($request->input('status'), function ($query, $status)
        {
            $query->where('status_id',$status);
        })
        ->where('user_id',$user->id)
        ->with('user','location','office','division','department','area','typeActivity','status')
        ->paginate(25)
        ->withQueryString()
        ->through(fn($user) => [

            "id" => $user->id,
            "code" => $user->code,
            "type_activity_id" => $user->typeActivity->id,
            "type_activity_name" => $user->typeActivity->name,
            "area_name" => $user->area->name,
            "area_id" => $user->area->id,
            "status_id" => $user->status_id,
            "status_name" => $user->status->name,
            "title" => $user->title,
            "user_id" => $user->user_id,
            "user_name" => $user->user->name,
            "user_last_name" => $user->user->last_name,
            "today_date" => $user->today_date,
            "start_date" => $user->start_date,
            "end_date" => $user->end_date,
            "location_id" => $user->location_id,
            "location_name" => $user->location->name,
            "office_id" => $user->office_id,
            "office_name" => $user->office->name,
            "division_id" => $user->division_id,
            "division_name" => $user->division->name,
            "department_id" => $user->department_id,
            "department_name" => $user->department->name,
            "progress" => $user->progress,
            "observation" => $user->observation,

        ]);

        return $activities;
    }

    public function create($data)
    {
        $newCode = Activity::generateNewCode();
        $todayDate = Carbon::now();

        $user = auth()->user();


        $newActivity = Activity::create(
            [
            "code" => $newCode,
            "status_id" => $data["status_id"],
            "title" => $data["title"],
            "user_id" => $user->id,
            "today_date" => $todayDate,
            "start_date" => $data["start_date"],
            "end_date" => $data["end_date"],
            "location_id" => $data["location_id"],
            "office_id" => $data["office_id"],
            "division_id" => $data["division_id"],
            "department_id" => $data["department_id"],
            "progress" => $data["progress"],
            "observation" => $data["observation"],
            "area_id" => $data["area_id"],
            "type_activity_id" => $data["type_activity_id"],
            ]);

        $newActivity->load('user','location','office','division','department','area','typeActivity','status');

        $search = $this->generateSearch($newActivity);

        $newActivity->update(['search' => $search]);

    }

    public function update($data,$id)
    {
        $activity = Activity::find($id);
        $user = auth()->user();
        
        $activity->update(

            [
                "status_id" => $data["status_id"],
                "title" => $data["title"],
                "user_id" => $user->id,
                "start_date" => $data["start_date"],
                "end_date" => $data["end_date"],
                "location_id" => $data["location_id"],
                "office_id" => $data["office_id"],
                "division_id" => $data["division_id"],
                "department_id" => $data["department_id"],
                "progress" => $data["progress"],
                "observation" => $data["observation"],
                "area_id" => $data["area_id"],
                "type_activity_id" => $data["type_activity_id"],
            ]
        );
    

        $activity->load('user','location','office','division','department','area','typeActivity','status');

        $search = $this->generateSearch($activity);
        
        $activity->update(['search' => $search]);

    }

    private function generateSearch($activity)
    {
        $search = 
        $activity->user->name . ' '
         . $activity->user->last_name . ' '
         . $activity->user->ci . ' '
         . $activity->location->name . ' '
         . $activity->office->name . ' '
         . $activity->division->name . ' '
         . $activity->department->name . ' '
         . $activity->area->name . ' '
         . $activity->typeActivity->name . ' '
         . $activity->title . ' '
         . $activity->status->name . ' ';

         return $search;
    }
    

}
