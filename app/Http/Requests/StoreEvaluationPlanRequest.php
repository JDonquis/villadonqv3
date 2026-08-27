<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEvaluationPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        $nullableFields = [
            'description',
        ];

        $data = $this->all();
        foreach ($nullableFields as $field) {
            if (! array_key_exists($field, $data)) {
                $this->merge([$field => null]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'matter_id' => ['required', 'integer', 'exists:matters,id'],
            'school_lapse_id' => ['required', 'integer', 'exists:school_lapses,id'],
            'lapse_id' => ['required', 'integer', 'exists:lapses,id'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:100'],
            'items.*.percentage' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'items.*.date' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $items = $this->input('items');

            if (! is_array($items)) {
                return;
            }

            $total = collect($items)->sum(fn ($item) => (float) ($item['percentage'] ?? 0));

            if ($total > 100) {
                $validator->errors()->add('items', 'La suma de los porcentajes no puede superar el 100%.');
            }
        });
    }
}
