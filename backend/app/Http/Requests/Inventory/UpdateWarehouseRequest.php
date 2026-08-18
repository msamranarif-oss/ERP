<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $warehouse = $this->route('warehouse');
        $tenantId = $this->user()->tenant_id;

        return [
            'name' => 'sometimes|required|string|max:255',
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('warehouses')->ignore($warehouse->id)->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                })
            ],
            'address' => 'sometimes|nullable|string|max:500',
            'phone' => 'sometimes|nullable|string|max:20',
            'branch_id' => 'sometimes|nullable|exists:branches,id',
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
