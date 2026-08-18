<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivityForTenant;

class RegisterSession extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes, LogsActivityForTenant;

    protected $fillable = [
        'tenant_id',
        'cash_register_id',
        'user_id',
        'closed_by',
        'opening_balance',
        'cash_sales',
        'card_sales',
        'other_sales',
        'refunds',
        'cash_in',
        'cash_out',
        'expected_balance',
        'closing_balance',
        'difference',
        'status',
        'opening_notes',
        'closing_notes',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'cash_sales' => 'decimal:2',
        'card_sales' => 'decimal:2',
        'other_sales' => 'decimal:2',
        'refunds' => 'decimal:2',
        'cash_in' => 'decimal:2',
        'cash_out' => 'decimal:2',
        'expected_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'difference' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Alias for the user who opened this session. */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The user who closed this session. */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function cashTransactions()
    {
        return $this->hasMany(CashTransaction::class);
    }

    public function getExpectedCashAttribute(): float
    {
        return (float) $this->calculateExpectedBalance();
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function calculateExpectedBalance(): float
    {
        return $this->opening_balance + $this->cash_sales - $this->refunds + $this->cash_in - $this->cash_out;
    }
}
