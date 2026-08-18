<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\BelongsToTenant;

class LoyaltyRule extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'spend_amount', 'points_earned', 'point_value', 'min_redeem_points', 'is_active',
    ];

    protected $casts = [
        'spend_amount'      => 'decimal:2',
        'points_earned'     => 'decimal:2',
        'point_value'       => 'decimal:4',
        'min_redeem_points' => 'decimal:2',
        'is_active'         => 'boolean',
    ];
}
