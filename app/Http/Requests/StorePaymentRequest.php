<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'account_payment_id' => 'required|exists:account_payments,id',
            'total_in_dolars' => 'required|numeric|min:0',
            'total_in_bs' => 'required|numeric|min:0',
            'reference' => 'nullable|string|max:50',
            'observations' => 'nullable|string',
            'students' => 'required|array|min:1',
            'students.*.id' => 'required|exists:students,id',
            'students.*.amount_in_dolars' => 'required|numeric|min:0',
            'students.*.balances' => 'required|array|min:1',
            'students.*.balances.*.id' => 'required|exists:balance_students,id',
            'reported_date' => 'nullable|date',
        ];
    }
}
