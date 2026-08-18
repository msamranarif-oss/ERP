<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivityForTenant;
use App\Traits\BelongsToTenant;

class SaleItem extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes, LogsActivityForTenant;

    protected $fillable = [
        'tenant_id',
        'sale_id',
        'product_id',
        'variant_id',
        'batch_id',
        'serial_number_id',
        'unit_id',
        'product_name',
        'quantity',
        'base_quantity',
        'conversion_factor',
        'unit_price',
        'discount',
        'discount_type',
        'tax',
        'tax_rate',
        'total',
        'cost_price',
        'profit',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'base_quantity' => 'decimal:4',
        'conversion_factor' => 'decimal:6',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'total' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'profit' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function serialNumber(): BelongsTo
    {
        return $this->belongsTo(SerialNumber::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
