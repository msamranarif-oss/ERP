<?php

namespace App\Http\Requests\Credit;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInstallmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'due_date' => 'sometimes|required|date',
            'amount' => 'sometimes|required|numeric|min:0.01',
            'status' => 'sometimes|required|in:unpaid,partially_paid,paid,overdue',
            'notes' => 'sometimes|nullable|string|max:1000',
        ];
    }
}
