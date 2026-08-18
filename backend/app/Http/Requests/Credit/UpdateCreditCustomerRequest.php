<?php

namespace App\Http\Requests\Credit;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCreditCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'credit_limit' => 'sometimes|required|numeric|min:0',
            'payment_terms' => 'sometimes|nullable|string|max:255',
            'status' => 'sometimes|required|in:active,suspended,closed',
            'notes' => 'sometimes|nullable|string|max:1000',
        ];
    }
}
