<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BundleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_bundle_id', 'product_id', 'variant_id', 'unit_id',
        'quantity', 'is_optional',
    ];

    protected $casts = [
        'quantity'    => 'decimal:4',
        'is_optional' => 'boolean',
    ];

    public function bundle(): BelongsTo  { return $this->belongsTo(ProductBundle::class, 'product_bundle_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class); }
    public function unit(): BelongsTo    { return $this->belongsTo(Unit::class); }
}
