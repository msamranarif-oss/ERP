<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingredient extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'category',
        'unit_id',
        'cost_per_unit',
        'minimum_stock_level',
        'maximum_stock_level',
        'is_active',
        'supplier_id',
        'sku',
        'barcode',
        'image_url',
    ];

    protected $casts = [
        'cost_per_unit' => 'decimal:2',
        'minimum_stock_level' => 'decimal:4',
        'maximum_stock_level' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function recipes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'recipe_ingredients')
                    ->withPivot(['quantity', 'unit_id', 'optional', 'notes'])
                    ->withTimestamps();
    }
}