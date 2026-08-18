<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallmentPlanTemplate extends Model
{
    protected $fillable = ['tenant_id', 'name', 'frequency', 'term', 'interest_rate', 'is_default', 'is_active'];
    protected $casts    = ['is_default' => 'boolean', 'is_active' => 'boolean', 'interest_rate' => 'decimal:2'];

    public function tenant() { return $this->belongsTo(Tenant::class); }
}
