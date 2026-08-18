<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivityForTenant;
use App\Services\LoggingService;

class Product extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes, LogsActivityForTenant;

    protected $fillable = [
        'tenant_id', 'category_id', 'base_unit_id', 'brand_id',
        'sku', 'barcode', 'name', 'description', 'short_description', 'image',
        'cost_price', 'selling_price', 'min_price', 'wholesale_price', 'max_price',
        'reorder_level', 'reorder_quantity', 'min_order_qty', 'max_order_qty',
        'is_active', 'is_featured', 'is_pos_visible', 'is_online_visible',
        'is_sellable', 'is_purchasable', 'track_inventory', 'has_variants',
        'allow_negative_stock', 'batch_tracking', 'serial_tracking', 'lot_tracking',
        'product_type', 'status', 'valuation_method',
        'tax_type', 'tax_rate', 'attributes', 'tags',
        'warranty_period', 'warranty_terms',
        'weight', 'length', 'width', 'height',
        'internal_notes',
    ];

    protected $casts = [
        'cost_price'          => 'decimal:2',
        'selling_price'       => 'decimal:2',
        'min_price'           => 'decimal:2',
        'wholesale_price'     => 'decimal:2',
        'max_price'           => 'decimal:2',
        'tax_rate'            => 'decimal:2',
        'weight'              => 'decimal:3',
        'length'              => 'decimal:3',
        'width'               => 'decimal:3',
        'height'              => 'decimal:3',
        'is_active'           => 'boolean',
        'is_featured'         => 'boolean',
        'is_pos_visible'      => 'boolean',
        'is_online_visible'   => 'boolean',
        'is_sellable'         => 'boolean',
        'is_purchasable'      => 'boolean',
        'track_inventory'     => 'boolean',
        'has_variants'        => 'boolean',
        'allow_negative_stock'=> 'boolean',
        'batch_tracking'      => 'boolean',
        'serial_tracking'     => 'boolean',
        'lot_tracking'        => 'boolean',
        'attributes'          => 'array',
        'tags'                => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function priceRules(): HasMany
    {
        return $this->hasMany(ProductPriceRule::class);
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

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class);
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
