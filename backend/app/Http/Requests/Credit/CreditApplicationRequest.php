<?php

namespace App\Http\Requests\Credit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class CreditApplicationRequest extends FormRequest
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
                'customer_id' => 'required|exists:customers,id',
                'requested_amount' => 'required|numeric|min:0',
                'purpose' => 'required|string|max:255',
                'notes' => 'nullable|string|max:500',
            ];
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            return [
                'customer_id' => 'sometimes|required|exists:customers,id',
                'requested_amount' => 'sometimes|required|numeric|min:0',
                'approved_amount' => 'nullable|numeric|min:0',
                'purpose' => 'sometimes|required|string|max:255',
                'status' => 'sometimes|in:pending,approved,rejected',
                'rejection_reason' => 'nullable|string|max:500',
                'notes' => 'nullable|string|max:500',
            ];
        }

        return [];
    }
}