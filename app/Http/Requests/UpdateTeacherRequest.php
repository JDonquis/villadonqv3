<?php

namespace App\Http\Requests;

use App\Enums\UserTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        $nullableFields = [
            'phone_number',
            'address',
            'matters',
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
        $userId = $this->route('id');

        return [
            'type_user_id' => ['required', 'integer', 'exists:type_users,id', Rule::in([UserTypeEnum::Teacher->value])],
            'ci' => ['required', 'string', 'max:30', 'unique:users,ci,'.$userId],
            'name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'string', 'max:100', 'email', 'unique:users,email,'.$userId],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:100'],
            'matters' => ['nullable', 'array'],
            'matters.*' => ['integer', 'exists:matters,id'],
        ];
    }
}
