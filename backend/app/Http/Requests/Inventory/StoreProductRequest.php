<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\BaseFormRequest;

class StoreProductRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        
        $booleans = [
            'is_active',
            'is_sellable',
            'is_purchasable',
            'track_inventory',
            'has_variants',
            'allow_negative_stock'
        ];

        foreach ($booleans as $key) {
            if ($this->has($key)) {
                $value = $this->input($key);
                $this->merge([
                    $key => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value
                ]);
            }
        }

        if ($this->has('image') && !$this->hasFile('image')) {
            $this->offsetUnset('image');
        }
    }

    public function rules(): array
    {
        // Additional security validation
        $this->validateSecurity();

        return [
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255|unique:products,sku,NULL,id,tenant_id,' . $this->user()->tenant_id,
            'barcode' => 'nullable|string|max:255|unique:products,barcode,NULL,id,tenant_id,' . $this->user()->tenant_id,
            'category_id' => 'nullable|exists:categories,id',
            'base_unit_id' => 'nullable|exists:units,id',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'cost_price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'min_price' => 'nullable|numeric|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'reorder_quantity' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'is_sellable' => 'boolean',
            'is_purchasable' => 'boolean',
            'track_inventory' => 'boolean',
            'has_variants' => 'boolean',
            'allow_negative_stock' => 'boolean',
            'tax_type' => 'nullable|in:inclusive,exclusive,exempt',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'attributes' => 'nullable|array',
        ];
    }

    /**
     * Additional security validation
     */
    protected function validateSecurity(): void
    {
        // Validate that prices are reasonable
        $fields = ['cost_price', 'selling_price', 'min_price', 'wholesale_price', 'max_price'];
        
        foreach ($fields as $field) {
            $value = $this->input($field, 0);
            if ($value > 999999999) {
                $this->merge([$field => 999999999]);
            }
        }

        // Validate numeric fields
        $numericFields = ['reorder_level', 'reorder_quantity', 'min_order_qty', 'max_order_qty'];
        foreach ($numericFields as $field) {
            $value = $this->input($field, 0);
            if ($value > 999999) {
                $this->merge([$field => 999999]);
            }
        }
    }
}
