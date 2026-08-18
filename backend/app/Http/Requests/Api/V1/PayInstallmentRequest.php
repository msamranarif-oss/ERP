<?php

namespace App\Http\Requests\Api\V1;

class PayInstallmentRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        // Access the installment from the route
        $installment = $this->route('installment');
        $maxAmount = $installment ? $installment->remaining_amount : 0;

        return [
            'amount' => 'required|numeric|min:0|max:' . $maxAmount,
            'payment_method_id' => 'required|exists:payment_methods,id',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
