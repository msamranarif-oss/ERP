<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $supplier = $this->route('supplier');
        $tenantId = $this->user()->tenant_id;

        return [
            'name' => 'sometimes|required|string|max:255',
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('suppliers')->ignore($supplier->id)->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                })
            ],
            'email' => 'sometimes|nullable|email|max:255',
            'phone' => 'sometimes|nullable|string|max:20',
            'address' => 'sometimes|nullable|string|max:500',
            'city' => 'sometimes|nullable|string|max:100',
            'country' => 'sometimes|nullable|string|max:100',
            'contact_person' => 'sometimes|nullable|string|max:255',
            'tax_number' => 'sometimes|nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
            'notes' => 'sometimes|nullable|string|max:1000',
        ];
    }
}
