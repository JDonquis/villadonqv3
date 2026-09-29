<?php

namespace App\Http\Requests;

use App\Models\EvaluationPlan;
use Closure;

class UpdateObservationRequest extends ObservationRequest
{
    public function plan(): ?EvaluationPlan
    {
        $observation = $this->route('observation');

        return $observation ? $observation->plan : null;
    }

    public function rules(): array
    {
        $rules = parent::rules();

        // Una observación nunca cambia de estudiante: se fija al original.
        $rules['student_id'] = [
            'required',
            'integer',
            function (string $attribute, mixed $value, Closure $fail): void {
                $observation = $this->route('observation');

                if ($observation && (int) $value !== (int) $observation->student_id) {
                    $fail('Una observación no se puede mover a otro estudiante.');
                }
            },
        ];

        return $rules;
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'student_id.required' => 'La observación ya no tiene estudiante asignado.',
        ]);
    }
}
