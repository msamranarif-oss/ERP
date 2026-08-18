<?php

namespace App\Http\Requests\Api\V1;

class PaymentRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'credit_sale_id' => 'required|exists:credit_sales,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
            'reference_number' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'credit_sale_id.required' => 'Credit sale ID is required.',
            'credit_sale_id.exists' => 'Selected credit sale does not exist.',
            'amount.required' => 'Payment amount is required.',
            'amount.numeric' => 'Payment amount must be a number.',
            'amount.min' => 'Payment amount must be at least 0.01.',
            'payment_method_id.required' => 'Payment method is required.',
            'payment_method_id.exists' => 'Selected payment method does not exist.',
            'payment_date.required' => 'Payment date is required.',
            'payment_date.date' => 'Payment date must be a valid date.',
        ];
    }
}