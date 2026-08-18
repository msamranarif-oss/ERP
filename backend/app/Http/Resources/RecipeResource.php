<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RecipeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'prep_time_minutes' => $this->prep_time_minutes,
            'cook_time_minutes' => $this->cook_time_minutes,
            'total_time_minutes' => $this->total_time_minutes,
            'servings' => $this->servings,
            'instructions' => $this->instructions,
            'nutritional_calories' => $this->nutritional_calories,
            'nutritional_protein' => $this->nutritional_protein,
            'nutritional_carbs' => $this->nutritional_carbs,
            'nutritional_fat' => $this->nutritional_fat,
            'image_url' => $this->image_url,
            'is_active' => $this->is_active,
            'category' => $this->category,
            'difficulty_level' => $this->difficulty_level,
            'seasonal_availability' => $this->seasonal_availability,
            'product' => $this->whenLoaded('product', function () {
                return $this->product ? [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'code' => $this->product->code,
                ] : null;
            }),
            'ingredients' => $this->whenLoaded('ingredients', function () {
                return $this->ingredients->map(function ($ingredient) {
                    return [
                        'id' => $ingredient->id,
                        'name' => $ingredient->name,
                        'quantity' => $ingredient->pivot->quantity,
                        'unit' => $this->whenLoaded('unit', function () use ($ingredient) {
                            return $ingredient->unit ? [
                                'id' => $ingredient->unit->id,
                                'name' => $ingredient->unit->name,
                                'short_name' => $ingredient->unit->short_name,
                            ] : null;
                        }),
                        'optional' => $ingredient->pivot->optional,
                        'notes' => $ingredient->pivot->notes,
                    ];
                });
            }),
            'toppings' => $this->whenLoaded('toppings', function () {
                return $this->toppings->map(function ($topping) {
                    return [
                        'id' => $topping->id,
                        'name' => $topping->name,
                        'is_required' => $topping->pivot->is_required,
                        'default_selected' => $topping->pivot->default_selected,
                    ];
                });
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}