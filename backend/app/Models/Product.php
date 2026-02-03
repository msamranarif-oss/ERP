<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'base_unit_id',
        'sku',
        'barcode',
        'name',
        'description',
        'image',
        'cost_price',
        'selling_price',
        'min_price',
        'reorder_level',
        'reorder_quantity',
        'is_active',
        'is_sellable',
        'is_purchasable',
        'track_inventory',
        'has_variants',
        'allow_negative_stock',
        'tax_type',
        'tax_rate',
        'attributes',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'min_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'is_active' => 'boolean',
        'is_sellable' => 'boolean',
        'is_purchasable' => 'boolean',
        'track_inventory' => 'boolean',
        'has_variants' => 'boolean',
        'allow_negative_stock' => 'boolean',
        'attributes' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSellable($query)
    {
        return $query->where('is_sellable', true)->where('is_active', true);
    }

    public function scopePurchasable($query)
    {
        return $query->where('is_purchasable', true)->where('is_active', true);
    }

    public function getTotalStockAttribute(): float
    {
        return $this->stockLevels->sum('quantity');
    }

    public function getAvailableStockAttribute(): float
    {
        return $this->stockLevels->sum(function ($level) {
            return $level->quantity - $level->reserved_quantity;
        });
    }
}
