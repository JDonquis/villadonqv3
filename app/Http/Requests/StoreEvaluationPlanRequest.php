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
            'section_id' => ['required', 'array', 'min:1'],
            'section_id.*' => ['required'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'units' => ['required_without:items', 'array', 'min:1'],
            'units.*.name' => ['nullable', 'string', 'max:100'],
            'units.*.unit_number' => ['nullable', 'integer', 'min:1'],
            'units.*.topics' => ['required', 'array', 'min:1'],
            'units.*.topics.*.name' => ['required', 'string', 'max:150'],
            'units.*.topics.*.assessment_type' => ['nullable', 'string', 'max:150'],
            'units.*.topics.*.percentage' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'units.*.topics.*.points' => ['nullable', 'numeric', 'min:0'],
            'units.*.topics.*.scheduled_date' => ['nullable', 'date'],
            'units.*.topics.*.description' => ['nullable', 'string'],
            'items' => ['required_without:units', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:100'],
            'items.*.percentage' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'items.*.date' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $units = $this->input('units');
            $items = $this->input('items');
            $flattened = [];

            if (is_array($units) && ! empty($units)) {
                foreach ($units as $unit) {
                    foreach ($unit['topics'] ?? [] as $topic) {
                        $flattened[] = $topic;
                    }
                }
            } elseif (is_array($items)) {
                $flattened = $items;
            }

            if ($flattened === []) {
                return;
            }

            $total = collect($flattened)->sum(fn ($item) => (float) ($item['percentage'] ?? 0));

            if ($total > 100) {
                $validator->errors()->add('units', 'La suma de los porcentajes no puede superar el 100%.');
            }

            // Validate section_id values: accept array with 'all' or existing section ids
            $sectionIds = $this->input('section_id');

            if (! is_array($sectionIds) || empty($sectionIds)) {
                $validator->errors()->add('section_id', 'Debe seleccionar al menos una sección.');
                return;
            }

            if (in_array('all', $sectionIds, true)) {
                // 'all' is allowed by frontend; no further checks here
                return;
            }

            $ids = array_values(array_filter($sectionIds, function ($v) { return $v !== null && $v !== ''; }));

            if (empty($ids)) {
                $validator->errors()->add('section_id', 'Debe seleccionar al menos una sección.');
                return;
            }

            $count = \App\Models\Section::whereIn('id', $ids)->count();
            if ($count !== count($ids)) {
                $validator->errors()->add('section_id', 'Se seleccionaron secciones inválidas.');
            }
        });
    }
}
