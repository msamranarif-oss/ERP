<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'account_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function account(): BelongsTo { return $this->belongsTo(ChartOfAccount::class, 'account_id'); }
    public function expenses(): HasMany  { return $this->hasMany(Expense::class); }
}
