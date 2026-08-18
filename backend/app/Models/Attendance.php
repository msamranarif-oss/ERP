<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = ['tenant_id','employee_id','date','check_in','check_out','hours_worked','overtime_hours','status','leave_request_id','notes'];
    protected $casts    = ['date' => 'date', 'hours_worked' => 'decimal:2', 'overtime_hours' => 'decimal:2'];

    public function scopeTenant($q)  { return $q->where('tenant_id', auth()->user()?->tenant_id); }
    public function employee(): BelongsTo     { return $this->belongsTo(Employee::class); }
    public function leaveRequest(): BelongsTo { return $this->belongsTo(LeaveRequest::class); }
}
