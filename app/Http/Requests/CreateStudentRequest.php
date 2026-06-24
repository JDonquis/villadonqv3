<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CreateStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, Rule|array|string>
     */
    public function prepareForValidation(): void
    {
        $nullableFields = [
            'student_email',
            'student_phone_number',
            'student_sex',
            'student_previous_school',
            'state',
            'city',
            'address',
            'rep_ci',
            'rep_document_type',
            'rep_phone_number',
            'rep_phone_number2',
            'rep_email',
            'rep_profession',
            'rep_workplace',
            'rep_relationship',
            'second_rep_relationship',
            'second_rep_name',
            'second_rep_last_name',
            'second_rep_ci',
            'second_rep_phone_number',
            'second_rep_phone_number2',
            'second_rep_email',
            'second_rep_profession',
            'second_rep_workplace',
            'is_exempt',
            'exemption_percentage',
            'exemption_observations',
            'document_type',
            'second_document_type',
            'student_document_type'
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

            'student_name' => ['required'],
            'student_last_name' => ['required'],
            'student_date_birth' => ['required'],
            'student_email' => ['nullable'],
            'student_ci' => ['required', 'unique:students,ci'],
            'student_phone_number' => ['nullable'],
            'student_sex' => ['nullable'],
            'student_previous_school' => ['nullable'],
            'course_id' => ['required'],
            'section_id' => ['required'],
            'state' => ['nullable'],
            'city' => ['nullable'],
            'address' => ['nullable'],
            'rep_id' => ['nullable'],
            'rep_name' => ['required'],
            'rep_last_name' => ['required'],
            'rep_ci' => ['required', 'unique:users,ci'],
            'rep_document_type' => ['nullable', 'string'],
            'rep_phone_number' => ['nullable'],
            'rep_phone_number2' => ['nullable'],
            'rep_email' => ['required', 'email', 'unique:users,email'],
            'rep_profession' => ['nullable'],
            'rep_workplace' => ['nullable'],
            'rep_relationship' => ['nullable'],
            'second_rep_relationship' => ['nullable'],
            'second_rep_name' => ['nullable'],
            'second_rep_last_name' => ['nullable'],
            'second_rep_ci' => ['nullable'],
            'second_rep_document_type' => ['nullable', 'string'],
            'second_rep_phone_number' => ['nullable'],
            'second_rep_phone_number2' => ['nullable'],
            'second_rep_email' => ['nullable'],
            'second_rep_profession' => ['nullable'],
            'second_rep_workplace' => ['nullable'],
            'is_exempt' => ['nullable', 'boolean'],
            'exemption_percentage' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
                \Illuminate\Validation\Rule::requiredIf(fn() => (bool) $this->input('is_exempt')),
            ],
            'exemption_observations' => ['nullable', 'string'],
            'document_type' => ['nullable', 'string'],
            'second_document_type' => ['nullable', 'string'],
            'student_document_type' => ['nullable', 'string'],
        ];
    }
}
