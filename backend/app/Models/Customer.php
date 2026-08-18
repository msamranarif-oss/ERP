<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivityForTenant;

class Customer extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes, LogsActivityForTenant;

    protected $fillable = [
        'tenant_id', 'code', 'name', 'email', 'phone',
        'address', 'city', 'country', 'tax_number',
        'customer_type', 'customer_group',
        'credit_limit', 'balance', 'loyalty_points', 'points',
        'cnic', 'photo',
        'date_of_birth', 'is_active',
        'is_blacklisted', 'blacklist_reason', 'blacklisted_at',
        'notes',
    ];

    protected $casts = [
        'credit_limit'   => 'decimal:2',
        'balance'        => 'decimal:2',
        'loyalty_points' => 'decimal:2',
        'date_of_birth'  => 'date',
        'blacklisted_at' => 'date',
        'is_active'      => 'boolean',
        'is_blacklisted' => 'boolean',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function creditSales(): HasMany
    {
        return $this->hasMany(CreditSale::class);
    }

    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function creditCustomer(): HasOne
    {
        return $this->hasOne(CreditCustomer::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRetail($query)
    {
        return $query->where('customer_type', 'retail');
    }

    public function scopeWholesale($query)
    {
        return $query->where('customer_type', 'wholesale');
    }

    public function getAvailableCreditAttribute(): float
    {
        return max(0, $this->credit_limit - $this->balance);
    }
}
