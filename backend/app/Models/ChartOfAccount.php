<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivityForTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccount extends Model
{
    use BelongsToTenant, HasFactory, LogsActivityForTenant, SoftDeletes;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'account_type_id',
        'code',
        'system_slug',
        'name',
        'description',
        'is_active',
        'is_system',
        'allow_direct_posting',
        'opening_balance',
        'current_balance',
        'level',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'allow_direct_posting' => 'boolean',
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_id');
    }

    public function accountType(): BelongsTo
    {
        return $this->belongsTo(AccountType::class);
    }

    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    public function journalEntries(): HasManyThrough
    {
        return $this->hasManyThrough(
            JournalEntry::class,
            JournalEntryLine::class,
            'account_id',
            'id',
            'id',
            'journal_entry_id'
        );
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePostable($query)
    {
        return $query->where('allow_direct_posting', true);
    }

    public function isDebitNormal(): bool
    {
        return $this->accountType->normal_balance === 'debit';
    }
}
