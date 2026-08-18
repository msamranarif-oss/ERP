<?php

namespace App\Models;

use App\Traits\LogsActivityForTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentItem extends Model
{
    use HasFactory, LogsActivityForTenant;

    protected $fillable = [
        'stock_adjustment_id',
        'product_id',
        'variant_id',
        'unit_id',
        'quantity_before',
        'quantity_after',
        'difference',
        'unit_cost',
        'notes',
    ];

    protected $casts = [
        'quantity_before' => 'decimal:4',
        'quantity_after' => 'decimal:4',
        'difference' => 'decimal:4',
        'unit_cost' => 'decimal:2',
    ];

    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
