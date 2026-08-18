<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpeningStockEntry extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'warehouse_id', 'entry_date', 'reference',
        'status', 'approved_by', 'approved_at', 'created_by',
    ];

    protected $casts = [
        'entry_date'  => 'date',
        'approved_at' => 'datetime',
    ];

    public function warehouse(): BelongsTo  { return $this->belongsTo(Warehouse::class); }
    public function approver(): BelongsTo   { return $this->belongsTo(User::class, 'approved_by'); }
    public function creator(): BelongsTo    { return $this->belongsTo(User::class, 'created_by'); }
    public function items(): HasMany        { return $this->hasMany(OpeningStockItem::class); }

    public function isDraft(): bool    { return $this->status === 'draft'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
}
