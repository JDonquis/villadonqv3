<?php

namespace App\Http\Requests;

use App\Models\StudentCharge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChargePaymentRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'account_payment_id' => 'required|exists:account_payments,id',
            'reference' => 'nullable|string|max:50',
            'observations' => 'nullable|string',
            'reported_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.student_id' => 'required|exists:students,id',
            'items.*.type' => ['required', Rule::in([StudentCharge::TYPE_AME, StudentCharge::TYPE_INVESTMENT_PLAN])],
            'items.*.amount_in_dolars' => 'required|numeric|min:0.01',
            'items.*.amount_in_bs' => 'nullable|numeric|min:0',
        ];
    }
}
