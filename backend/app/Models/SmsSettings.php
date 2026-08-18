<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsSettings extends Model
{
    protected $fillable = ['tenant_id', 'gateway', 'api_key', 'api_secret', 'sender_id', 'is_active'];
    protected $hidden   = ['api_key', 'api_secret'];
    protected $casts    = ['is_active' => 'boolean'];

    public function tenant() { return $this->belongsTo(Tenant::class); }
}
