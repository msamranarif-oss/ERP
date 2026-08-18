<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLoan extends Model
{
    protected $fillable = ['tenant_id','employee_id','amount','balance','monthly_deduction','start_date','end_date','status','notes'];
    protected $casts    = ['amount' => 'decimal:2', 'balance' => 'decimal:2', 'monthly_deduction' => 'decimal:2', 'start_date' => 'date', 'end_date' => 'date'];

    public function scopeTenant($q) { return $q->where('tenant_id', auth()->user()?->tenant_id); }
    public function scopeActive($q) { return $q->where('status', 'active'); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
