<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantSetting extends Model
{
    protected $fillable = [
        'tenant_id',
        'company_name',
        'address',
        'phone',
        'email',
        'tax_number',
        'logo_path',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
