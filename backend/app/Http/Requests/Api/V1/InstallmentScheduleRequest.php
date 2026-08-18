<?php

namespace App\Http\Requests\Api\V1;

class InstallmentScheduleRequest extends BaseRequest
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
            'schedule_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'status' => 'required|in:pending,paid,overdue',
            'due_date' => 'required|date',
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
            'schedule_date.required' => 'Schedule date is required.',
            'schedule_date.date' => 'Schedule date must be a valid date.',
            'amount.required' => 'Amount is required.',
            'amount.numeric' => 'Amount must be a number.',
            'amount.min' => 'Amount must be at least 0.01.',
            'status.required' => 'Status is required.',
            'status.in' => 'Status must be pending, paid, or overdue.',
            'due_date.required' => 'Due date is required.',
            'due_date.date' => 'Due date must be a valid date.',
        ];
    }
}