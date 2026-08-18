<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailSettings extends Model
{
    protected $fillable = [
        'tenant_id', 'driver', 'host', 'port', 'username', 'password',
        'encryption', 'from_address', 'from_name', 'is_active',
    ];
    protected $hidden = ['password'];
    protected $casts  = ['is_active' => 'boolean', 'port' => 'integer'];

    public function tenant() { return $this->belongsTo(Tenant::class); }
}
