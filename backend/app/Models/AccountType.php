<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\LogsActivityForTenant;

class AccountType extends Model
{
    use HasFactory, LogsActivityForTenant;

    protected $fillable = [
        'name',
        'category',
        'normal_balance',
    ];

    public function accounts(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class);
    }

    public function isDebitNormal(): bool
    {
        return $this->normal_balance === 'debit';
    }

    public function isCreditNormal(): bool
    {
        return $this->normal_balance === 'credit';
    }
}
