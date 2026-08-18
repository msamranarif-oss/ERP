<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\LogsActivityForTenant;

class HeldSale extends Model
{
    use HasFactory, BelongsToTenant, LogsActivityForTenant;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'register_session_id',
        'customer_id',
        'reference', // Maps to hold_number in controller
        'items',     // Legacy JSON, now we use relation
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total',
        'notes',
        'held_by',   // Maps to created_by in controller
        'status',    // Need to add this to migration if not exists, but we can manage it
        'restaurant_table_id',
        'order_type', // dine-in, takeaway, delivery
        'sale_id',    // set when the held sale is converted to a real sale
    ];

    protected $casts = [
        'items'           => 'array',
        'subtotal'        => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount'      => 'decimal:2',
        'total'           => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function heldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'held_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function registerSession(): BelongsTo
    {
        return $this->belongsTo(RegisterSession::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(HeldSaleItem::class);
    }

    public function restaurantTable(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    /** The Sale created when this held sale was closed/billed. */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
}
