<?php

namespace App\Http\Requests\Credit;

use Illuminate\Foundation\Http\FormRequest;

class VerifyCreditCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'verification_data' => 'required|array',
            'verification_data.id_type' => 'required|string',
            'verification_data.id_number' => 'required|string',
        ];
    }
}
