<?php

namespace App\Models;

use App\Traits\LogsActivityForTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliation extends Model
{
    use HasFactory, LogsActivityForTenant;

    protected $fillable = [
        'bank_account_id',
        'statement_date',
        'statement_opening_balance',
        'statement_closing_balance',
        'system_balance',
        'difference',
        'outstanding_checks',
        'deposits_in_transit',
        'bank_charges',
        'interest_earned',
        'status', // pending, completed
        'notes',
        'created_by',
        'completed_by',
        'completed_at',
    ];

    protected $casts = [
        'statement_date' => 'date',
        'statement_opening_balance' => 'decimal:2',
        'statement_closing_balance' => 'decimal:2',
        'system_balance' => 'decimal:2',
        'difference' => 'decimal:2',
        'outstanding_checks' => 'array',
        'deposits_in_transit' => 'array',
        'bank_charges' => 'array',
        'interest_earned' => 'array',
        'completed_at' => 'datetime',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
