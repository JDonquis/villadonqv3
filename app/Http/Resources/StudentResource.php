<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return
            [
                'student_document_type' => $this->document_type,
                'student_id' => $this->id,
                'student_name' => $this->name,
                'student_last_name' => $this->last_name,
                'student_age' => $this->getAge($this->date_birth),
                'student_ci' => $this->ci ?? null,
                'student_email' => $this->email ?? null,
                'student_date_birth' => $this->date_birth,
                'student_phone_number' => $this->phone_number ?? null,
                'student_sex' => $this->sex,
                'course_id' => $this->course_id,
                'course_name' => $this->course->name,
                'section_id' => $this->section_id,
                'section_name' => $this->section->name,
                'address' => $this->representative->user->address ?? null,
                'rep_name' => $this->representative->user->name,
                'rep_last_name' => $this->representative->user->last_name,
                'rep_id' => $this->representative->id,
                'rep_ci' => $this->representative->user->ci,
                'rep_phone_number' => $this->representative->user->phone_number,
                'rep_phone_number2' => $this->representative->user->phone_number2 ?? null,
                'rep_email' => $this->representative->user->email ?? null,
                'rep_relationship' => $this->representative->relationship ?? null,
                'rep_document_type' => $this->representative->document_type ?? null,
                'state' => $this->representative->user->state ?? null,
                'city' => $this->representative->user->city ?? null,
                'second_rep_relationship' => $this->representative->second_representative_relationship ?? null,
                'second_rep_name' => $this->representative->second_representative_name ?? null,
                'second_rep_last_name' => $this->representative->second_representative_last_name ?? null,
                'second_rep_ci' => $this->representative->second_representative_ci ?? null,
                'second_rep_phone_number' => $this->representative->second_representative_phone_number ?? null,
                'second_rep_phone_number2' => $this->representative->second_representative_phone_number2 ?? null,
                'second_rep_email' => $this->representative->second_representative_email ?? null,
                'second_rep_document_type' => $this->representative->second_document_type ?? null,
                'is_exempt' => $this->is_exempt,
                'exemption_percentage' => $this->exemption_percentage,
                'exemption_observations' => $this->exemption_observations,
                'apply_to_past_debts' => $this->apply_to_past_debts,

            ];
    }

    private function getAge($dateOfBirth)
    {
        $today = Carbon::now();
        $diff = $today->diffInYears($dateOfBirth);

        return $diff;
    }
}
