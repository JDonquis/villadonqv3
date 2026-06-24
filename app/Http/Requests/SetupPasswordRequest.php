<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetupPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => 'required|string|size:64',
            'password' => 'required|string|min:8|confirmed',
        ];
    }
}
