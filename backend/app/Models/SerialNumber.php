<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerialNumber extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'product_id', 'variant_id', 'batch_id', 'warehouse_id',
        'serial_number', 'imei', 'status',
        'sale_item_id', 'sold_to', 'sold_at', 'warranty_expiry',
        'notes', 'created_by',
    ];

    protected $casts = [
        'sold_at'         => 'datetime',
        'warranty_expiry' => 'date',
    ];

    public function product(): BelongsTo   { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo   { return $this->belongsTo(ProductVariant::class); }
    public function batch(): BelongsTo     { return $this->belongsTo(Batch::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function customer(): BelongsTo  { return $this->belongsTo(Customer::class, 'sold_to'); }

    public function scopeInStock($query)   { return $query->where('status', 'in_stock'); }
    public function scopeSold($query)      { return $query->where('status', 'sold'); }
    public function scopeDefective($query) { return $query->where('status', 'defective'); }

    public function scopeWarrantyExpiringSoon($query, int $days = 30)
    {
        return $query->where('warranty_expiry', '>', now())
                     ->where('warranty_expiry', '<=', now()->addDays($days));
    }
}
