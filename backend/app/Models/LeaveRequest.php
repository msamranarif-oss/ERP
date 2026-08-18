<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    protected $fillable = ['tenant_id','employee_id','leave_type_id','start_date','end_date','days','reason','status','approved_by','approved_at','notes'];
    protected $casts    = ['start_date' => 'date', 'end_date' => 'date', 'approved_at' => 'datetime', 'days' => 'decimal:1'];

    public function scopeTenant($q) { return $q->where('tenant_id', auth()->user()?->tenant_id); }
    public function employee(): BelongsTo   { return $this->belongsTo(Employee::class); }
    public function leaveType(): BelongsTo  { return $this->belongsTo(LeaveType::class); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
