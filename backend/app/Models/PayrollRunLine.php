<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRunLine extends Model
{
    protected $fillable = ['payroll_run_id','salary_component_id','component_name','type','amount'];
    protected $casts    = ['amount' => 'decimal:2'];

    public function run(): BelongsTo            { return $this->belongsTo(PayrollRun::class, 'payroll_run_id'); }
    public function component(): BelongsTo      { return $this->belongsTo(SalaryComponent::class, 'salary_component_id'); }
}
