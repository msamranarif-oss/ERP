<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_id', 'product_id', 'variant_id', 'unit_id',
        'quantity', 'unit_price', 'discount_percent', 'total',
    ];

    protected $casts = [
        'quantity'         => 'decimal:4',
        'unit_price'       => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'total'            => 'decimal:2',
    ];

    public function quotation(): BelongsTo { return $this->belongsTo(Quotation::class); }
    public function product(): BelongsTo   { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo   { return $this->belongsTo(ProductVariant::class); }
    public function unit(): BelongsTo      { return $this->belongsTo(Unit::class); }
}
