<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $fillable = ['tenant_id','name','code','days_allowed_per_year','carry_forward','max_carry_forward_days','is_paid','requires_approval','is_active'];
    protected $casts    = ['carry_forward' => 'boolean', 'is_paid' => 'boolean', 'requires_approval' => 'boolean', 'is_active' => 'boolean'];

    public function scopeTenant($q) { return $q->where('tenant_id', auth()->user()?->tenant_id); }
    public function requests(): HasMany  { return $this->hasMany(LeaveRequest::class); }
    public function balances(): HasMany  { return $this->hasMany(LeaveBalance::class); }
}
