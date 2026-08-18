<?php

namespace App\Http\Requests\Credit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class PaymentReminderRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(Request $request): array
    {
        $method = $request->method();
        
        if ($method === 'POST') {
            return [
                'credit_sale_id' => 'required|exists:credit_sales,id',
                'installment_schedule_id' => 'nullable|exists:installment_schedules,id',
                'type' => 'required|in:sms,email,call',
                'scheduled_at' => 'required|date',
                'message' => 'required|string|max:500',
            ];
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            return [
                'credit_sale_id' => 'sometimes|required|exists:credit_sales,id',
                'installment_schedule_id' => 'nullable|exists:installment_schedules,id',
                'type' => 'sometimes|required|in:sms,email,call',
                'scheduled_at' => 'sometimes|required|date',
                'message' => 'sometimes|required|string|max:500',
                'status' => 'sometimes|in:pending,sent,failed',
            ];
        }

        return [];
    }
}