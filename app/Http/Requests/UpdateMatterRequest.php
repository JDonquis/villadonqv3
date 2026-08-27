<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMatterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $matterId = $this->route('id');

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('matters', 'name')->ignore($matterId)],
        ];
    }
}
