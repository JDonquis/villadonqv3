<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountStatementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->student->id,
            'name' => $this->student->name,
            'last_name' => $this->student->last_name,
            'ci' => $this->student->ci,
            'phone_number' => $this->student->phone_number,
            'is_exempt' => $this->student->is_exempt,
            'exemption_percentage' => $this->student->exemption_percentage,
            'exemption_observations' => $this->student->exemption_observations,
            'course' => $this->student->course,
            'section' => $this->student->section,
            'representative' => $this->student->representative,
            'balances' => $this->balances,
            'total_debt' => $this->totalDebt,
            'total_income' => $this->totalIncome,
        ];
    }
}
