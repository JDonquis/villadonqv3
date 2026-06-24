<?php

namespace App\Http\Requests;

use App\Enums\UserTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
            'photo',
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
            'type_user_id' => ['required', 'integer', 'exists:type_users,id', Rule::in([UserTypeEnum::Administrator->value])], // Permitir solo el tipo de usuario "Administrador"
            'ci' => ['required', 'string', 'max:30', 'unique:users,ci'],
            'name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'unique:users,email', 'string', 'max:100', 'email'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:100'],
            // 'photo' => ['nullable', 'string', 'max:100'],
            'is_admin' => ['required', 'boolean'],
        ];
    }
}
