<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionRule extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = ['tenant_id', 'user_id', 'rate_percent', 'is_active'];

    protected $casts = ['rate_percent' => 'decimal:2', 'is_active' => 'boolean'];

    public function user(): BelongsTo         { return $this->belongsTo(User::class); }
    public function commissions(): HasMany     { return $this->hasMany(SaleCommission::class); }

    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeGlobal($q) { return $q->whereNull('user_id'); }
}
