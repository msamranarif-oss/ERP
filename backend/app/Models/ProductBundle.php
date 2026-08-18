<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductBundle extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'product_id', 'pricing_type',
        'discount_amount', 'discount_percent',
        'promo_valid_from', 'promo_valid_to', 'is_active',
    ];

    protected $casts = [
        'discount_amount'  => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'promo_valid_from' => 'date',
        'promo_valid_to'   => 'date',
        'is_active'        => 'boolean',
    ];

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function items(): HasMany     { return $this->hasMany(BundleItem::class); }
}
