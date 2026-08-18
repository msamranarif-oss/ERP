<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivityForTenant;

class ProductUnit extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes, LogsActivityForTenant;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'unit_id',
        'conversion_factor',
        'selling_price',
        'cost_price',
        'barcode',
        'is_purchase_unit',
        'is_sale_unit',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:6',
        'selling_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_purchase_unit' => 'boolean',
        'is_sale_unit' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
