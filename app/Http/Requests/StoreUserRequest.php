<?php

namespace App\Http\Requests;

use App\Enums\UserTypeEnum;
use App\Models\User;
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

    private function existingUserId(): ?int
    {
        $email = $this->input('email');
        $ci = $this->input('ci');

        if (! is_string($email) || ! is_string($ci) || $email === '' || $ci === '') {
            return null;
        }

        return User::where('email', $email)->where('ci', $ci)->value('id');
    }

    public function rules(): array
    {
        $existingUserId = $this->existingUserId();

        $ciRule = Rule::unique('users', 'ci')->whereNull('deleted_at');
        $emailRule = Rule::unique('users', 'email')->whereNull('deleted_at');

        if ($existingUserId) {
            $ciRule->ignore($existingUserId);
            $emailRule->ignore($existingUserId);
        }

        return [
            'type_user_id' => ['required', 'integer', 'exists:type_users,id', Rule::in([UserTypeEnum::Administrator->value])], // Permitir solo el tipo de usuario "Administrador"
            'ci' => ['required', 'string', 'max:30', $ciRule],
            'name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'string', 'max:100', 'email', $emailRule],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:100'],
            // 'photo' => ['nullable', 'string', 'max:100'],
            'is_admin' => ['required', 'boolean'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['integer', 'exists:modules,id'],
        ];
    }
}
