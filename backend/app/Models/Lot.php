<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lot extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'supplier_id', 'lot_number',
        'received_date', 'quantity',
        'qc_status', 'qc_notes', 'qc_reviewed_at', 'qc_reviewed_by',
        'status',
    ];

    protected $casts = [
        'received_date'  => 'date',
        'qc_reviewed_at' => 'datetime',
        'quantity'       => 'decimal:4',
    ];

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'qc_reviewed_by'); }
    public function batches(): HasMany    { return $this->hasMany(Batch::class); }

    public function scopePending($query)     { return $query->where('qc_status', 'pending'); }
    public function scopeAvailable($query)   { return $query->where('status', 'available'); }
    public function scopeQuarantine($query)  { return $query->where('status', 'quarantine'); }
}
