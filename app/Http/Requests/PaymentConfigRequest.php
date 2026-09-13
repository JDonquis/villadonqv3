<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class PaymentConfigRequest extends FormRequest
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
    public function rules(): array
    {
        return [
            'regular_inscription_price' => ['required'],
            'new_inscription_price' => ['required'],
            'preescolar_inscription_price' => ['nullable', 'integer', 'min:0'],
            'primaria_inscription_price' => ['nullable', 'integer', 'min:0'],
            'secundaria_inscription_price' => ['nullable', 'integer', 'min:0'],
            'monthly_payment' => ['required'],
            'ame_price' => ['required'],
            'investment_plan_price' => ['required'],
            'day_of_monthly_payment' => ['required', 'integer'],
            'grace_period' => ['required', 'integer'],
            'payment_carton_price' => ['nullable'],
        ];
    }
}
