<?php

namespace App\Http\Requests\Api\V1;

class InstallmentRequest extends BaseRequest
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
            'due_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'paid_amount' => 'required|numeric|min:0|lte:amount',
            'status' => 'required|in:pending,paid,overdue',
            'notes' => 'nullable|string|max:500',
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
            'due_date.required' => 'Due date is required.',
            'due_date.date' => 'Due date must be a valid date.',
            'amount.required' => 'Amount is required.',
            'amount.numeric' => 'Amount must be a number.',
            'amount.min' => 'Amount must be at least 0.01.',
            'paid_amount.required' => 'Paid amount is required.',
            'paid_amount.numeric' => 'Paid amount must be a number.',
            'paid_amount.lte' => 'Paid amount cannot exceed the total amount.',
            'status.required' => 'Status is required.',
            'status.in' => 'Status must be pending, paid, or overdue.',
        ];
    }
}