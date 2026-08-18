<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Department extends Model
{
    protected $fillable = ['tenant_id','parent_id','manager_id','name','description','is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function scopeTenant($q) { return $q->where('tenant_id', auth()->user()?->tenant_id); }
    public function parent(): BelongsTo  { return $this->belongsTo(Department::class, 'parent_id'); }
    public function children(): HasMany  { return $this->hasMany(Department::class, 'parent_id'); }
    public function manager(): BelongsTo { return $this->belongsTo(Employee::class, 'manager_id'); }
    public function positions(): HasMany  { return $this->hasMany(Position::class); }
    public function employees(): HasMany  { return $this->hasMany(Employee::class); }
}
