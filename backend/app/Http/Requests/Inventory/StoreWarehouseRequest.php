<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:warehouses,code,NULL,id,tenant_id,' . $this->user()->tenant_id,
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'branch_id' => 'nullable|exists:branches,id',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
