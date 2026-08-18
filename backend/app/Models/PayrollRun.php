<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    protected $fillable = [
        'tenant_id','payroll_period_id','employee_id',
        'working_days','days_worked','days_absent','leave_days_paid','leave_days_unpaid','overtime_hours',
        'basic_salary','gross_earnings','total_deductions','loan_deductions','tax_amount','net_pay',
        'status','notes',
    ];
    protected $casts = [
        'basic_salary' => 'decimal:2', 'gross_earnings' => 'decimal:2',
        'total_deductions' => 'decimal:2', 'loan_deductions' => 'decimal:2',
        'tax_amount' => 'decimal:2', 'net_pay' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
    ];

    public function scopeTenant($q) { return $q->where('tenant_id', auth()->user()?->tenant_id); }
    public function period(): BelongsTo   { return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id'); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function lines(): HasMany      { return $this->hasMany(PayrollRunLine::class); }
    public function earnings(): HasMany   { return $this->hasMany(PayrollRunLine::class)->where('type', 'earning'); }
    public function deductions(): HasMany { return $this->hasMany(PayrollRunLine::class)->whereIn('type', ['deduction', 'tax']); }
}
