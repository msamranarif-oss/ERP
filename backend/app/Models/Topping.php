<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Topping extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'category_id',
        'unit_id',
        'cost_per_unit',
        'selling_price',
        'is_active',
        'is_available',
        'image_url',
        'max_allowed',
        'min_required',
        'sort_order',
    ];

    protected $casts = [
        'cost_per_unit' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_available' => 'boolean',
        'max_allowed' => 'integer',
        'min_required' => 'integer',
        'sort_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function recipes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'recipe_toppings')
                    ->withPivot(['is_required', 'default_selected'])
                    ->withTimestamps();
    }
}