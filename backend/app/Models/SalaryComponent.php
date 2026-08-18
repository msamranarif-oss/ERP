<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryComponent extends Model
{
    protected $fillable = ['tenant_id','name','code','type','calculation_type','default_value','taxable','is_statutory','is_active','description'];
    protected $casts    = ['taxable' => 'boolean', 'is_statutory' => 'boolean', 'is_active' => 'boolean', 'default_value' => 'decimal:2'];

    public function scopeTenant($q) { return $q->where('tenant_id', auth()->user()?->tenant_id); }
    public function employeeComponents(): HasMany { return $this->hasMany(EmployeeSalaryComponent::class); }
}
