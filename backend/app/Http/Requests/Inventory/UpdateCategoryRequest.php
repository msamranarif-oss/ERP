<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('category');
        $tenantId = $this->user()->tenant_id;

        return [
            'name' => 'sometimes|required|string|max:255|unique:categories,name,' . $category->id . ',id,tenant_id,' . $tenantId,
            'parent_id' => 'sometimes|nullable|exists:categories,id',
            'description' => 'sometimes|nullable|string',
            'image' => 'sometimes|nullable|image|max:2048',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
