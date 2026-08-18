<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    protected $fillable = ['tenant_id','department_id','title','description','min_salary','max_salary','is_active'];
    protected $casts = ['is_active' => 'boolean', 'min_salary' => 'decimal:2', 'max_salary' => 'decimal:2'];

    public function scopeTenant($q) { return $q->where('tenant_id', auth()->user()?->tenant_id); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function employees(): HasMany    { return $this->hasMany(Employee::class); }
}
