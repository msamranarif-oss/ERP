<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivityForTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankTransaction extends Model
{
    use BelongsToTenant, HasFactory, LogsActivityForTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'bank_account_id',
        'type',
        'amount',
        'balance_after',
        'reference',
        'description',
        'transaction_date',
        'reference_type',
        'reference_id',
        'is_reconciled',
        'reconciled_at',
        'reconciled_by',
        'journal_entry_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'transaction_date' => 'date',
        'is_reconciled' => 'boolean',
        'reconciled_at' => 'datetime',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
