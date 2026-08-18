<?php

namespace App\Models;

use App\Traits\LogsActivityForTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    use HasFactory, LogsActivityForTenant;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'variant_id',
        'unit_id',
        'quantity',
        'received_quantity',
        'unit_price',
        'discount',
        'tax',
        'total',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'received_quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function grnItems(): HasMany
    {
        return $this->hasMany(GRNItem::class, 'purchase_order_item_id');
    }

    public function getReceivedQuantityTotalAttribute()
    {
        return $this->grnItems()->sum('quantity_received');
    }
}
