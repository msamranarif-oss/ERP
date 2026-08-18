<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unit = $this->route('unit');
        $tenantId = $this->user()->tenant_id;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('units')->ignore($unit->id)->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                })
            ],
            'abbreviation' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('units')->ignore($unit->id)->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                })
            ],
            'is_base' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
