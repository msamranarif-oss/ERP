<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoyaltyTransactionRequest extends FormRequest
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
    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'sale_id' => 'nullable|exists:sales,id',
            'type' => 'required|in:earned,redeemed',
            'points' => 'required|integer|min:0',
            'description' => 'nullable|string|max:500',
            'reference' => 'nullable|string|max:100',
            'transaction_date' => 'required|date',
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'Customer ID is required.',
            'customer_id.exists' => 'Selected customer does not exist.',
            'type.required' => 'Transaction type is required.',
            'type.in' => 'Transaction type must be either earned or redeemed.',
            'points.required' => 'Points amount is required.',
            'points.integer' => 'Points must be a whole number.',
            'transaction_date.required' => 'Transaction date is required.',
        ];
    }
}