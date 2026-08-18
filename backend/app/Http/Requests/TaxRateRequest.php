<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class TaxRateRequest extends FormRequest
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
    public function rules(Request $request): array
    {
        $method = $request->method();
        
        if ($method === 'POST') {
            return [
                'name' => 'required|string|max:100',
                'rate' => 'required|numeric|min:0|max:100',
                'type' => 'required|in:inclusive,exclusive',
                'is_default' => 'boolean',
                'is_active' => 'boolean',
                'description' => 'nullable|string|max:255',
            ];
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            return [
                'name' => 'sometimes|required|string|max:100',
                'rate' => 'sometimes|required|numeric|min:0|max:100',
                'type' => 'sometimes|required|in:inclusive,exclusive',
                'is_default' => 'boolean',
                'is_active' => 'boolean',
                'description' => 'nullable|string|max:255',
            ];
        }

        return [];
    }
}