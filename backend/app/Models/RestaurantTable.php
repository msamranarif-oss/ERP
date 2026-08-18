<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class RestaurantTable extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'capacity',
        'area_name',
        'status',   // available | occupied | billed | reserved
    ];

    protected $casts = [
        'capacity' => 'integer',
    ];

    // ── Relationships ────────────────────────────────────

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function heldSales(): HasMany
    {
        return $this->hasMany(HeldSale::class, 'restaurant_table_id');
    }

    /** The current active (held) order on this table. */
    public function activeOrder(): HasOne
    {
        return $this->hasOne(HeldSale::class, 'restaurant_table_id')
            ->where('status', 'held')
            ->latestOfMany();
    }

    // ── Scopes ───────────────────────────────────────────

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available');
    }

    public function scopeOccupied(Builder $query): Builder
    {
        return $query->where('status', 'occupied');
    }

    public function scopeByArea(Builder $query, string $area): Builder
    {
        return $query->where('area_name', $area);
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    // ── Helpers ──────────────────────────────────────────

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function isOccupied(): bool
    {
        return in_array($this->status, ['occupied', 'billed']);
    }
}
