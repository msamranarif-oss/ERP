<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class BudgetRequest extends FormRequest
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
                'account_id' => 'required|exists:chart_of_accounts,id',
                'fiscal_year_id' => 'required|exists:fiscal_years,id',
                'period_type' => 'required|in:month,quarter,year',
                'period_value' => 'required|integer|min:1|max:12', // month number, quarter number, or year
                'amount' => 'required|numeric|min:0',
                'notes' => 'nullable|string|max:500',
            ];
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            return [
                'account_id' => 'sometimes|required|exists:chart_of_accounts,id',
                'fiscal_year_id' => 'sometimes|required|exists:fiscal_years,id',
                'period_type' => 'sometimes|required|in:month,quarter,year',
                'period_value' => 'sometimes|required|integer|min:1|max:12',
                'amount' => 'sometimes|required|numeric|min:0',
                'notes' => 'nullable|string|max:500',
            ];
        }

        return [];
    }
}