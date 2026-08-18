<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabelTemplate extends Model
{
    protected $fillable = ['tenant_id', 'name', 'size', 'fields', 'is_default'];
    protected $casts    = ['fields' => 'array', 'is_default' => 'boolean'];

    public function tenant() { return $this->belongsTo(Tenant::class); }
}
