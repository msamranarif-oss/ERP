<?php

namespace App\Http\Requests\Api\V1;

class UpdateCreditCustomerRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'credit_limit' => 'sometimes|required|numeric|min:0',
            'interest_rate' => 'sometimes|required|numeric|min:0|max:100',
            'grace_period_days' => 'sometimes|required|integer|min:0|max:365',
            'late_fee_percent' => 'sometimes|required|numeric|min:0|max:100',
            'max_installments' => 'sometimes|required|integer|min:1|max:120',
            'notes' => 'sometimes|nullable|string|max:1000',
            'status' => 'sometimes|required|in:active,inactive,suspended',
        ];
    }
}
