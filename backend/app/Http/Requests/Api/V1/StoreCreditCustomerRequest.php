<?php

namespace App\Http\Requests\Api\V1;

class StoreCreditCustomerRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'credit_limit' => 'required|numeric|min:0',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'grace_period_days' => 'required|integer|min:0|max:365',
            'late_fee_percent' => 'required|numeric|min:0|max:100',
            'max_installments' => 'required|integer|min:1|max:120',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
