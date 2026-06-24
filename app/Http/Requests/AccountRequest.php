<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class AccountRequest extends FormRequest
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
            'person_name',
            'email',
            'ci',
            'phone_number',
            'bank',
            'account_number',
            'username',
            'comision',
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
            'payment_method_id' => ['required'],
            'person_name' => ['nullable'],
            'email' => ['nullable'],
            'ci' => ['nullable'],
            'phone_number' => ['nullable'],
            'bank' => ['nullable'],
            'account_number' => ['nullable'],
            'username' => ['nullable'],
            'email' => ['nullable'],
            'comision' => ['nullable', 'numeric'],

        ];
    }
}
