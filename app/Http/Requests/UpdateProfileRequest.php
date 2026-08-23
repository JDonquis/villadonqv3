<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        $nullableFields = [
            'email',
            'phone_number',
            'phone_number2',
            'address',
            'state',
            'city',
            'document_type',
            'profession',
            'workplace',
            'relationship',
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
        $userId = $this->user()->id;

        return [
            'name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'ci' => ['required', 'string', 'max:30', Rule::unique('users', 'ci')->ignore($userId)],
            'email' => ['nullable', 'string', 'max:100', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'phone_number2' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:50'],
            'document_type' => ['nullable', 'string', 'max:5'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:2048'],
            'profession' => ['nullable', 'string', 'max:100'],
            'workplace' => ['nullable', 'string', 'max:100'],
            'relationship' => ['nullable', 'string', 'max:100'],
        ];
    }
}
