<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Customer;
use Illuminate\Validation\Rule;

class StoreCreditSaleFormRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'customer_id' => [
                'required',
                'integer',
                'exists:customers,id',
                function ($attribute, $value, $fail) {
                    $customer = Customer::find($value);
                    if ($customer && $customer->tenant_id !== auth()->user()->tenant_id) {
                        $fail('The selected customer is invalid.');
                    }
                }
            ],
            'down_payment' => 'required|numeric|min:0',
            'loan_amount' => 'required|numeric|min:0',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'number_of_installments' => 'required|integer|min:1|max:120',
            'installment_frequency' => [
                'required',
                Rule::in(['weekly', 'biweekly', 'monthly', 'quarterly'])
            ],
            'first_installment_date' => 'required|date|after:today',
            'total_amount' => 'required|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.tax_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'customer_id' => 'customer',
            'down_payment' => 'down payment',
            'loan_amount' => 'loan amount',
            'interest_rate' => 'interest rate',
            'number_of_installments' => 'number of installments',
            'installment_frequency' => 'installment frequency',
            'first_installment_date' => 'first installment date',
            'total_amount' => 'total amount',
            'discount_amount' => 'discount amount',
            'tax_amount' => 'tax amount',
            'shipping_cost' => 'shipping cost',
            'items.*.product_id' => 'product',
            'items.*.quantity' => 'quantity',
            'items.*.unit_price' => 'unit price',
            'items.*.discount_percent' => 'discount percent',
            'items.*.discount_amount' => 'discount amount',
            'items.*.tax_percent' => 'tax percent',
            'items.*.tax_amount' => 'tax amount',
        ];
    }
}