<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\LogsActivityForTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use BelongsToTenant, HasFactory, LogsActivityForTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'unit_category_id',
        'name',
        'abbreviation',
        'symbol',
        'conversion_factor',
        'is_base',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:6',
        'is_base' => 'boolean',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'base_unit_id');
    }

    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function conversionsFrom(): HasMany
    {
        return $this->hasMany(UnitConversion::class, 'from_unit_id');
    }

    public function conversionsTo(): HasMany
    {
        return $this->hasMany(UnitConversion::class, 'to_unit_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
