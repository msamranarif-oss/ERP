<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningStockItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'opening_stock_entry_id', 'product_id', 'variant_id',
        'batch_id', 'unit_id', 'quantity', 'unit_cost',
    ];

    protected $casts = [
        'quantity'  => 'decimal:4',
        'unit_cost' => 'decimal:2',
    ];

    public function entry(): BelongsTo   { return $this->belongsTo(OpeningStockEntry::class, 'opening_stock_entry_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class); }
    public function batch(): BelongsTo   { return $this->belongsTo(Batch::class); }
    public function unit(): BelongsTo    { return $this->belongsTo(Unit::class); }
}
