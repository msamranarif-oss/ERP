<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManufacturingOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'manufacturing_order_id',
        'product_id',
        'variant_id',
        'quantity_planned',
        'quantity_consumed',
        'unit_cost',
    ];

    protected $casts = [
        'quantity_planned' => 'decimal:4',
        'quantity_consumed' => 'decimal:4',
        'unit_cost' => 'decimal:2',
    ];

    public function manufacturingOrder(): BelongsTo
    {
        return $this->belongsTo(ManufacturingOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
