<?php

namespace App\Http\Requests;

use App\Models\EvaluationPlan;

class StoreObservationRequest extends ObservationRequest
{
    public function plan(): ?EvaluationPlan
    {
        $planId = $this->input('evaluation_plan_id');

        return $planId ? EvaluationPlan::find((int) $planId) : null;
    }

    public function rules(): array
    {
        return array_merge(
            ['evaluation_plan_id' => ['required', 'integer', 'exists:evaluation_plans,id']],
            parent::rules()
        );
    }
}
