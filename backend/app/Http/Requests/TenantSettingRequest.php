<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TenantSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'key' => 'required|string|max:255|unique:tenant_settings,key,' . ($this->tenant_setting ? $this->tenant_setting->id : 'NULL'),
            'value' => 'required',
            'group' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'key.required' => 'Setting key is required.',
            'key.unique' => 'A setting with this key already exists.',
            'value.required' => 'Setting value is required.',
        ];
    }
}