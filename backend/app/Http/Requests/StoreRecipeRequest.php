<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Adjust authorization logic as needed
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'prep_time_minutes' => 'nullable|integer|min:0',
            'cook_time_minutes' => 'nullable|integer|min:0',
            'total_time_minutes' => 'nullable|integer|min:0',
            'servings' => 'required|integer|min:1',
            'instructions' => 'nullable|array',
            'instructions.*' => 'string',
            'nutritional_calories' => 'nullable|numeric|min:0',
            'nutritional_protein' => 'nullable|numeric|min:0',
            'nutritional_carbs' => 'nullable|numeric|min:0',
            'nutritional_fat' => 'nullable|numeric|min:0',
            'image_url' => 'nullable|url',
            'is_active' => 'boolean',
            'category' => 'nullable|string|max:100',
            'difficulty_level' => 'nullable|in:easy,medium,hard',
            'seasonal_availability' => 'nullable|string|max:100',
            'product_id' => 'nullable|exists:products,id',
            'ingredients' => 'nullable|array',
            'ingredients.*.ingredient_id' => 'required|exists:ingredients,id',
            'ingredients.*.quantity' => 'required|numeric|min:0.0001',
            'ingredients.*.unit_id' => 'nullable|exists:units,id',
            'ingredients.*.optional' => 'boolean',
            'ingredients.*.notes' => 'nullable|string',
            'toppings' => 'nullable|array',
            'toppings.*.topping_id' => 'required|exists:toppings,id',
            'toppings.*.is_required' => 'boolean',
            'toppings.*.default_selected' => 'boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Recipe name',
            'servings' => 'Number of servings',
            'ingredients.*.ingredient_id' => 'Ingredient',
            'ingredients.*.quantity' => 'Quantity',
            'toppings.*.topping_id' => 'Topping',
        ];
    }
}