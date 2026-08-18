<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivityForTenant;

class TenantConfiguration extends Model
{
    use HasFactory, SoftDeletes, LogsActivityForTenant;

    protected $fillable = [
        'tenant_id',
        'key',
        'value',
        'group',
        'description',
        'is_system',
        'is_encrypted',
        'validation_rules',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_encrypted' => 'boolean',
        'validation_rules' => 'array',
        'value' => 'encrypted',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    public function scopeUser($query)
    {
        return $query->where('is_system', false);
    }

    public function getValueAttribute($value)
    {
        if ($this->is_encrypted) {
            return decrypt($value);
        }
        return $value;
    }

    public function setValueAttribute($value)
    {
        if ($this->is_encrypted) {
            $this->attributes['value'] = encrypt($value);
        } else {
            $this->attributes['value'] = $value;
        }
    }

    public function isValidValue($value): bool
    {
        if (!$this->validation_rules) {
            return true;
        }

        $validator = \Illuminate\Support\Facades\Validator::make(
            ['value' => $value],
            ['value' => $this->validation_rules]
        );

        return $validator->passes();
    }
}