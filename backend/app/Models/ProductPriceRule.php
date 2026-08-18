<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\BelongsToTenant;

class ProductPriceRule extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'product_id', 'variant_id', 'customer_type',
        'price', 'valid_from', 'valid_to', 'is_active',
    ];

    protected $casts = [
        'price'      => 'decimal:2',
        'valid_from' => 'date',
        'valid_to'   => 'date',
        'is_active'  => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
