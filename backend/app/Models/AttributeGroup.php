<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\BelongsToTenant;

class AttributeGroup extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function values()
    {
        return $this->hasMany(AttributeValue::class, 'attribute_group_id');
    }
}
