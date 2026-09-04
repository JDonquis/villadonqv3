<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGradesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', 'exists:evaluation_plans,id'],
            'grades' => ['required', 'array'],
            'grades.*.plan_item_id' => ['required', 'integer'],
            'grades.*.student_id' => ['required', 'integer'],
            'grades.*.score' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'rasgos' => ['nullable', 'array'],
            'rasgos.*.student_id' => ['required', 'integer'],
            'rasgos.*.rasgos_score' => ['nullable', 'integer', 'min:0', 'max:10'],
        ];
    }
}
