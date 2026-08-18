<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'account_id', 'fiscal_year_id',
        'period_type', 'period_value', 'amount', 'notes',
    ];

    protected $casts = ['amount' => 'decimal:2'];

    public function account(): BelongsTo    { return $this->belongsTo(ChartOfAccount::class, 'account_id'); }
    public function fiscalYear(): BelongsTo { return $this->belongsTo(FiscalYear::class); }
}
