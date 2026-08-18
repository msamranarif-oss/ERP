<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeSalary extends Model
{
    protected $fillable = ['tenant_id','employee_id','basic_salary','effective_date','currency','pay_frequency','notes','is_current'];
    protected $casts    = ['basic_salary' => 'decimal:2', 'effective_date' => 'date', 'is_current' => 'boolean'];

    public function employee(): BelongsTo   { return $this->belongsTo(Employee::class); }
    public function components(): HasMany   { return $this->hasMany(EmployeeSalaryComponent::class); }
}
