<?php

namespace App\Http\Requests\Api\V1;

use App\Models\PaymentMethod;

class RecordCreditSalePaymentFormRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'installment_id' => [
                'required',
                'integer',
                'exists:installments,id',
            ],
            'payment_method_id' => [
                'required',
                'integer',
                'exists:payment_methods,id',
                function ($attribute, $value, $fail) {
                    $paymentMethod = PaymentMethod::find($value);
                    if ($paymentMethod && !$paymentMethod->is_active) {
                        $fail('The selected payment method is inactive.');
                    }
                    if ($paymentMethod && $paymentMethod->tenant_id !== auth()->user()->tenant_id) {
                        $fail('The selected payment method is invalid.');
                    }
                }
            ],
            'amount' => 'required|numeric|min:0.01',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'installment_id' => 'installment',
            'payment_method_id' => 'payment method',
            'amount' => 'payment amount',
            'reference' => 'reference number',
            'notes' => 'notes',
        ];
    }
}