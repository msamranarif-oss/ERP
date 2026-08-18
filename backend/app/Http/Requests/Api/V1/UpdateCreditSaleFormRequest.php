<?php

namespace App\Http\Requests\Api\V1;

class UpdateCreditSaleFormRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'down_payment' => 'sometimes|numeric|min:0',
            'loan_amount' => 'sometimes|numeric|min:0',
            'interest_rate' => 'sometimes|numeric|min:0|max:100',
            'number_of_installments' => 'sometimes|integer|min:1|max:120',
            'installment_frequency' => [
                'sometimes',
                'in:weekly,biweekly,monthly,quarterly'
            ],
            'first_installment_date' => 'sometimes|date|after:today',
            'total_amount' => 'sometimes|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'status' => 'sometimes|in:active,completed,cancelled,defaulted',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'down_payment' => 'down payment',
            'loan_amount' => 'loan amount',
            'interest_rate' => 'interest rate',
            'number_of_installments' => 'number of installments',
            'installment_frequency' => 'installment frequency',
            'first_installment_date' => 'first installment date',
            'total_amount' => 'total amount',
            'discount_amount' => 'discount amount',
            'tax_amount' => 'tax amount',
            'shipping_cost' => 'shipping cost',
            'status' => 'status',
        ];
    }
}