<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [

            "id" => $user->id,
            "code" => $user->code,
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
        ];
    }
}
