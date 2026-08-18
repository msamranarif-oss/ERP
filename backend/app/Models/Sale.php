<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivityForTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use BelongsToTenant, HasFactory, LogsActivityForTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'warehouse_id',
        'customer_id',
        'register_session_id',
        'sale_number',
        'type',
        'sale_date',
        'subtotal',
        'discount_amount',
        'discount_type',
        'discount_value',
        'tax_amount',
        'shipping_amount',
        'total',
        'cogs_amount',
        'paid_amount',
        'change_amount',
        'balance_due',
        'payment_status',
        'status',
        'accounting_status',
        'accounting_failure_reason',
        'notes',
        'internal_notes',
        'sold_by',
        'voided_by',
        'voided_at',
        'void_reason',
        'coupon_id',
        'coupon_discount_amount',
        'points_redeemed',
        'loyalty_discount_amount',
        'restaurant_table_id',
        'order_type',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'cogs_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'voided_at' => 'datetime',
        'coupon_discount_amount' => 'decimal:2',
        'points_redeemed' => 'decimal:2',
        'loyalty_discount_amount' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function registerSession(): BelongsTo
    {
        return $this->belongsTo(RegisterSession::class);
    }

    public function soldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function creditSale()
    {
        return $this->hasOne(CreditSale::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function restaurantTable(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('sale_date', today());
    }

    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }
}
