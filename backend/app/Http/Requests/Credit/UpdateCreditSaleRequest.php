<?php

namespace App\Http\Requests\Credit;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCreditSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'sometimes|required|in:active,completed,cancelled',
            'notes' => 'sometimes|nullable|string|max:1000',
        ];
    }
}
