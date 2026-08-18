<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'customer_id', 'quotation_number', 'quotation_date',
        'valid_until', 'status', 'subtotal', 'discount_amount',
        'tax_amount', 'total_amount', 'notes', 'created_by', 'sale_id',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'valid_until'    => 'date',
        'subtotal'       => 'decimal:2',
        'discount_amount'=> 'decimal:2',
        'tax_amount'     => 'decimal:2',
        'total_amount'   => 'decimal:2',
    ];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function creator(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }
    public function sale(): BelongsTo     { return $this->belongsTo(Sale::class); }
    public function items(): HasMany      { return $this->hasMany(QuotationItem::class); }

    public function isExpired(): bool { return $this->valid_until && $this->valid_until->isPast(); }
}
