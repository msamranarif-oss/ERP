<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivityForTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseBill extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes, LogsActivityForTenant;

    protected $fillable = [
        'tenant_id',
        'purchase_order_id',
        'supplier_id',
        'bill_number',
        'bill_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'shipping_cost',
        'total',
        'paid_amount',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseBillItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Compute total based on actual received quantities from GRN items.
     * Falls back to stored total if GRN relations are not loaded.
     */
    public function getReceivedTotalAttribute(): float
    {
        if (!$this->relationLoaded('items')) {
            return (float) $this->total;
        }

        $computed = $this->items->sum(function ($item) {
            if ($item->relationLoaded('purchaseOrderItem') && $item->purchaseOrderItem?->relationLoaded('grnItems')) {
                $receivedQty = $item->purchaseOrderItem->grnItems->sum('quantity_received');
            } else {
                $receivedQty = (float) $item->quantity;
            }
            return ($receivedQty * (float) $item->unit_price) + (float) ($item->tax ?? 0);
        });

        return round($computed + (float) ($this->shipping_cost ?? 0), 2);
    }
}
