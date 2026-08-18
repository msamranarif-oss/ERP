<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\Ingredient;
use App\Models\Topping;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class RecipeService
{
    public function getAllRecipes(array $filters = [])
    {
        $query = Recipe::with(['product', 'ingredients.unit', 'toppings']);

        if (isset($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'LIKE', '%' . $filters['search'] . '%');
            });
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getRecipeById(int $id)
    {
        return Recipe::with(['product', 'ingredients.unit', 'toppings'])->findOrFail($id);
    }

    public function createRecipe(array $data)
    {
        return DB::transaction(function () use ($data) {
            $recipe = Recipe::create($this->prepareRecipeData($data));

            // Attach ingredients if provided
            if (isset($data['ingredients']) && is_array($data['ingredients'])) {
                foreach ($data['ingredients'] as $ingredientData) {
                    $ingredient = Ingredient::where('tenant_id', $data['tenant_id'])
                        ->findOrFail($ingredientData['ingredient_id']);

                    $recipe->ingredients()->attach($ingredient->id, [
                        'quantity' => $ingredientData['quantity'],
                        'unit_id' => $ingredientData['unit_id'] ?? null,
                        'optional' => $ingredientData['optional'] ?? false,
                        'notes' => $ingredientData['notes'] ?? null,
                    ]);
                }
            }

            // Attach toppings if provided
            if (isset($data['toppings']) && is_array($data['toppings'])) {
                foreach ($data['toppings'] as $toppingData) {
                    $topping = Topping::where('tenant_id', $data['tenant_id'])
                        ->findOrFail($toppingData['topping_id']);

                    $recipe->toppings()->attach($topping->id, [
                        'is_required' => $toppingData['is_required'] ?? false,
                        'default_selected' => $toppingData['default_selected'] ?? false,
                    ]);
                }
            }

            return $recipe->fresh(['product', 'ingredients.unit', 'toppings']);
        });
    }

    public function updateRecipe(int $id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $recipe = Recipe::findOrFail($id);
            $recipe->update($this->prepareRecipeData($data));

            // Update ingredients
            if (isset($data['ingredients']) && is_array($data['ingredients'])) {
                $recipe->ingredients()->detach();
                
                foreach ($data['ingredients'] as $ingredientData) {
                    $ingredient = Ingredient::where('tenant_id', $recipe->tenant_id)
                        ->findOrFail($ingredientData['ingredient_id']);

                    $recipe->ingredients()->attach($ingredient->id, [
                        'quantity' => $ingredientData['quantity'],
                        'unit_id' => $ingredientData['unit_id'] ?? null,
                        'optional' => $ingredientData['optional'] ?? false,
                        'notes' => $ingredientData['notes'] ?? null,
                    ]);
                }
            }

            // Update toppings
            if (isset($data['toppings']) && is_array($data['toppings'])) {
                $recipe->toppings()->detach();
                
                foreach ($data['toppings'] as $toppingData) {
                    $topping = Topping::where('tenant_id', $recipe->tenant_id)
                        ->findOrFail($toppingData['topping_id']);

                    $recipe->toppings()->attach($topping->id, [
                        'is_required' => $toppingData['is_required'] ?? false,
                        'default_selected' => $toppingData['default_selected'] ?? false,
                    ]);
                }
            }

            return $recipe->fresh(['product', 'ingredients.unit', 'toppings']);
        });
    }

    public function deleteRecipe(int $id)
    {
        $recipe = Recipe::findOrFail($id);
        return $recipe->delete();
    }

    public function getNutritionInfo(int $recipeId)
    {
        $recipe = Recipe::with('ingredients')->findOrFail($recipeId);
        
        $nutritionInfo = [
            'calories' => 0,
            'protein' => 0,
            'carbs' => 0,
            'fat' => 0,
        ];

        foreach ($recipe->ingredients as $ingredient) {
            // Calculate nutrition based on quantity and unit
            // This is a simplified calculation - in reality, you'd need actual nutritional data per ingredient
            $nutritionInfo['calories'] += $ingredient->pivot->quantity;
            $nutritionInfo['protein'] += $ingredient->pivot->quantity * 0.1; // placeholder calculation
            $nutritionInfo['carbs'] += $ingredient->pivot->quantity * 0.1; // placeholder calculation
            $nutritionInfo['fat'] += $ingredient->pivot->quantity * 0.1; // placeholder calculation
        }

        return $nutritionInfo;
    }

    private function prepareRecipeData(array $data): array
    {
        $preparedData = [
            'tenant_id' => $data['tenant_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'prep_time_minutes' => $data['prep_time_minutes'] ?? null,
            'cook_time_minutes' => $data['cook_time_minutes'] ?? null,
            'total_time_minutes' => $data['total_time_minutes'] ?? null,
            'servings' => $data['servings'],
            'instructions' => $data['instructions'] ?? [],
            'nutritional_calories' => $data['nutritional_calories'] ?? null,
            'nutritional_protein' => $data['nutritional_protein'] ?? null,
            'nutritional_carbs' => $data['nutritional_carbs'] ?? null,
            'nutritional_fat' => $data['nutritional_fat'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'category' => $data['category'] ?? null,
            'difficulty_level' => $data['difficulty_level'] ?? 'medium',
            'seasonal_availability' => $data['seasonal_availability'] ?? null,
        ];

        // Handle product_id separately
        if (isset($data['product_id'])) {
            $preparedData['product_id'] = $data['product_id'];
        }

        return $preparedData;
    }
}