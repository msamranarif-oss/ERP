<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recipe extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'name',
        'description',
        'prep_time_minutes',
        'cook_time_minutes',
        'total_time_minutes',
        'servings',
        'instructions',
        'nutritional_calories',
        'nutritional_protein',
        'nutritional_carbs',
        'nutritional_fat',
        'image_url',
        'is_active',
        'category',
        'difficulty_level',
        'seasonal_availability',
    ];

    protected $casts = [
        'prep_time_minutes' => 'integer',
        'cook_time_minutes' => 'integer',
        'total_time_minutes' => 'integer',
        'servings' => 'integer',
        'nutritional_calories' => 'decimal:2',
        'nutritional_protein' => 'decimal:2',
        'nutritional_carbs' => 'decimal:2',
        'nutritional_fat' => 'decimal:2',
        'is_active' => 'boolean',
        'instructions' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'recipe_ingredients')
                    ->withPivot(['quantity', 'unit_id', 'optional', 'notes'])
                    ->withTimestamps();
    }

    public function toppings(): BelongsToMany
    {
        return $this->belongsToMany(Topping::class, 'recipe_toppings')
                    ->withPivot(['is_required', 'default_selected'])
                    ->withTimestamps();
    }

    public function recipeIngredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    public function recipeToppings(): HasMany
    {
        return $this->hasMany(RecipeTopping::class);
    }
}