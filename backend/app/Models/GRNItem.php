<?php

namespace App\Models;

use App\Traits\LogsActivityForTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GRNItem extends Model
{
    use HasFactory, LogsActivityForTenant;

    protected $table = 'grn_items';

    protected $fillable = [
        'grn_id',
        'purchase_order_item_id',
        'product_id',
        'variant_id',
        'unit_id',
        'quantity_received',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:4',
    ];

    public function grn(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedNote::class, 'grn_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
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
