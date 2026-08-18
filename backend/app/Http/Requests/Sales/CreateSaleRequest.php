<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\BaseFormRequest;

class CreateSaleRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Additional security validation
        $this->validateSecurity();

        return [
            'customer_id' => 'nullable|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_id' => 'nullable|exists:units,id',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_percent' => 'nullable|numeric|min:0|max:100',
            'tax_amount' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'payment_amount' => 'nullable|numeric|min:0',
            'payments' => 'nullable|array',
            'payments.*.payment_method_id' => 'required|exists:payment_methods,id',
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.reference' => 'nullable|string|max:100',
            'type' => 'nullable|string|in:walk-in,credit,quotation',
            'change_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000|not_regex:/<script|javascript|onload|onerror/i',
            'register_session_id' => 'required|exists:register_sessions,id',
            'coupon_code' => 'nullable|string|max:50',
            'points_to_redeem' => 'nullable|numeric|min:0',
            'down_payment' => 'nullable|numeric|min:0',
            'installment_count' => 'nullable|integer|min:1',
            'installment_frequency' => 'nullable|string|in:daily,weekly,monthly',
        ];
    }

    /**
     * Additional security validation
     */
    protected function validateSecurity(): void
    {
        // Validate that amounts are reasonable
        $totalAmount = $this->input('total_amount', 0);
        $paidAmount = $this->input('paid_amount', 0);
        
        if ($totalAmount > 999999999) {
            $this->merge(['total_amount' => 999999999]);
        }
        
        if ($paidAmount > 999999999) {
            $this->merge(['paid_amount' => 999999999]);
        }

        // Validate item quantities
        $items = $this->input('items', []);
        foreach ($items as $index => $item) {
            if (isset($item['quantity']) && $item['quantity'] > 999999) {
                $items[$index]['quantity'] = 999999;
            }
            if (isset($item['unit_price']) && $item['unit_price'] > 999999999) {
                $items[$index]['unit_price'] = 999999999;
            }
        }
        $this->merge(['items' => $items]);
    }
}
