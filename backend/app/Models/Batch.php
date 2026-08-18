<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'product_id', 'variant_id', 'warehouse_id',
        'batch_number', 'manufacturing_date', 'expiry_date',
        'quantity_received', 'quantity_remaining', 'cost_price', 'selling_price',
        'supplier_id', 'grn_id', 'lot_id',
        'status', 'is_recalled', 'notes', 'created_by',
    ];

    protected $casts = [
        'manufacturing_date'  => 'date',
        'expiry_date'         => 'date',
        'quantity_received'   => 'decimal:4',
        'quantity_remaining'  => 'decimal:4',
        'cost_price'          => 'decimal:2',
        'selling_price'       => 'decimal:2',
        'is_recalled'         => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpired($query)
    {
        return $query->where('expiry_date', '<', now());
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('expiry_date', '>', now())
                     ->where('expiry_date', '<=', now()->addDays($days));
    }

    public function scopeWithStock($query)
    {
        return $query->where('quantity_remaining', '>', 0);
    }
}
