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
            'payment_concept_id' => 'nullable|exists:payment_concepts,id',
            'total_in_dolars' => 'required|numeric|min:0',
            'total_in_bs' => 'required|numeric|min:0',
            'reference' => 'nullable|string|max:50',
            'observations' => 'nullable|string',
            'students' => [
                'required', 'array', 'min:1',
                function ($attribute, $value, $fail) {
                    $hasConcept = ! empty($this->input('payment_concept_id'));
                    if ($hasConcept) {
                        return;
                    }
                    foreach ($value as $student) {
                        if (empty($student['balances'])) {
                            $fail('Cada estudiante de un pago regular debe tener al menos un periodo de balance.');
                            return;
                        }
                    }
                },
            ],
            'students.*.id' => 'required|exists:students,id',
            'students.*.amount_in_dolars' => 'required|numeric|min:0',
            'students.*.balances' => 'array',
            'students.*.balances.*.id' => 'exists:balance_students,id',
            'reported_date' => 'nullable|date',
        ];
    }
}
