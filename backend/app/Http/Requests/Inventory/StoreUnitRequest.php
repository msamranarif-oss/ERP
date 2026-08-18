<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'abbreviation' => 'required|string|max:20|unique:units,abbreviation,NULL,id,tenant_id,' . $this->user()->tenant_id,
            'is_base' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
