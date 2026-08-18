<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id','user_id','department_id','position_id','reports_to',
        'employee_number','full_name','first_name','last_name','gender',
        'date_of_birth','national_id','phone','email','address','photo',
        'hire_date','termination_date','contract_type','status','work_location',
        'bank_name','bank_account','bank_branch','tax_id','tax_exemption','notes','is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'date_of_birth' => 'date',
        'hire_date' => 'date',
        'termination_date' => 'date',
        'tax_exemption' => 'decimal:2',
    ];

    protected $hidden = ['bank_account', 'national_id', 'tax_id'];

    public function scopeTenant($q) { return $q->where('tenant_id', auth()->user()?->tenant_id); }
    public function scopeActive($q) { return $q->where('status', 'active'); }

    public function department(): BelongsTo  { return $this->belongsTo(Department::class); }
    public function position(): BelongsTo    { return $this->belongsTo(Position::class); }
    public function manager(): BelongsTo     { return $this->belongsTo(Employee::class, 'reports_to'); }
    public function user(): BelongsTo        { return $this->belongsTo(User::class); }

    public function currentSalary(): HasOne  { return $this->hasOne(EmployeeSalary::class)->where('is_current', true)->latestOfMany('effective_date'); }
    public function salaries(): HasMany      { return $this->hasMany(EmployeeSalary::class); }
    public function loans(): HasMany         { return $this->hasMany(EmployeeLoan::class); }
    public function leaveRequests(): HasMany { return $this->hasMany(LeaveRequest::class); }
    public function leaveBalances(): HasMany { return $this->hasMany(LeaveBalance::class); }
    public function attendance(): HasMany    { return $this->hasMany(Attendance::class); }
    public function payrollRuns(): HasMany   { return $this->hasMany(PayrollRun::class); }
}
