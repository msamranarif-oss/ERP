<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryComponent extends Model
{
    protected $fillable = ['employee_salary_id','salary_component_id','value','is_active'];
    protected $casts    = ['value' => 'decimal:2', 'is_active' => 'boolean'];

    public function salary(): BelongsTo    { return $this->belongsTo(EmployeeSalary::class, 'employee_salary_id'); }
    public function component(): BelongsTo { return $this->belongsTo(SalaryComponent::class); }
}
