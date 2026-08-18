<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    protected $fillable = ['tenant_id','employee_id','leave_type_id','year','allocated','used','pending','carried_forward'];
    protected $casts    = ['allocated' => 'decimal:2', 'used' => 'decimal:2', 'pending' => 'decimal:2', 'carried_forward' => 'decimal:2'];

    public function scopeTenant($q) { return $q->where('tenant_id', auth()->user()?->tenant_id); }
    public function employee(): BelongsTo  { return $this->belongsTo(Employee::class); }
    public function leaveType(): BelongsTo { return $this->belongsTo(LeaveType::class); }

    public function getAvailableAttribute(): float
    {
        return max(0, ($this->allocated + $this->carried_forward) - $this->used - $this->pending);
    }
}
