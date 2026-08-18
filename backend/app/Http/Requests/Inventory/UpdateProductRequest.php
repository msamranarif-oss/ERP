<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
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
        $product = $this->route('product');
        $tenantId = $this->user()->tenant_id;

        return [
            'name' => 'sometimes|required|string|max:255',
            'sku' => 'sometimes|nullable|string|max:255|unique:products,sku,' . $product->id . ',id,tenant_id,' . $tenantId,
            'barcode' => 'sometimes|nullable|string|max:255|unique:products,barcode,' . $product->id . ',id,tenant_id,' . $tenantId,
            'category_id' => 'sometimes|nullable|exists:categories,id',
            'base_unit_id' => 'sometimes|nullable|exists:units,id',
            'description' => 'sometimes|nullable|string',
            'image' => 'sometimes|nullable|image|max:2048',
            'cost_price' => 'sometimes|nullable|numeric|min:0',
            'selling_price' => 'sometimes|nullable|numeric|min:0',
            'min_price' => 'sometimes|nullable|numeric|min:0',
            'reorder_level' => 'sometimes|nullable|integer|min:0',
            'reorder_quantity' => 'sometimes|nullable|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'is_sellable' => 'sometimes|boolean',
            'is_purchasable' => 'sometimes|boolean',
            'track_inventory' => 'sometimes|boolean',
            'has_variants' => 'sometimes|boolean',
            'allow_negative_stock' => 'sometimes|boolean',
            'tax_type' => 'sometimes|nullable|in:inclusive,exclusive,exempt',
            'tax_rate' => 'sometimes|nullable|numeric|min:0|max:100',
            'attributes' => 'sometimes|nullable|array',
        ];
    }
}
