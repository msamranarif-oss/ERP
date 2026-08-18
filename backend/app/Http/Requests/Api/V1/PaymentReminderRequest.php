<?php

namespace App\Http\Requests\Api\V1;

class PaymentReminderRequest extends BaseRequest
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
            'installment_schedule_id' => 'nullable|exists:installment_schedules,id',
            'reminder_date' => 'required|date',
            'sent_date' => 'nullable|date',
            'status' => 'required|in:pending,sent,failed',
            'message' => 'nullable|string|max:1000',
            'contact_method' => 'nullable|in:email,sms,phone',
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
            'installment_schedule_id.exists' => 'Selected installment schedule does not exist.',
            'reminder_date.required' => 'Reminder date is required.',
            'reminder_date.date' => 'Reminder date must be a valid date.',
            'sent_date.date' => 'Sent date must be a valid date.',
            'status.required' => 'Status is required.',
            'status.in' => 'Status must be pending, sent, or failed.',
            'contact_method.in' => 'Contact method must be email, sms, or phone.',
        ];
    }
}