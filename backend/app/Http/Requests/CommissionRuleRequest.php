<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class CommissionRuleRequest extends FormRequest
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
                'user_id' => 'nullable|exists:users,id',
                'rate_percent' => 'required|numeric|min:0|max:100',
                'is_active' => 'boolean',
            ];
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            return [
                'user_id' => 'sometimes|nullable|exists:users,id',
                'rate_percent' => 'sometimes|required|numeric|min:0|max:100',
                'is_active' => 'boolean',
            ];
        }

        return [];
    }
}