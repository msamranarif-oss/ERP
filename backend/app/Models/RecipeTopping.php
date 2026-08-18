<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeTopping extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipe_id',
        'topping_id',
        'is_required',
        'default_selected',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'default_selected' => 'boolean',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function topping(): BelongsTo
    {
        return $this->belongsTo(Topping::class);
    }
}