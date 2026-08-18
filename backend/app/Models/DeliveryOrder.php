<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOrder extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'sale_id', 'customer_id', 'delivery_number', 'status',
        'delivery_address', 'scheduled_at', 'dispatched_at', 'delivered_at',
        'driver_name', 'vehicle_number', 'notes',
    ];

    protected $casts = [
        'scheduled_at'  => 'datetime',
        'dispatched_at' => 'datetime',
        'delivered_at'  => 'datetime',
    ];

    public function sale(): BelongsTo     { return $this->belongsTo(Sale::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }

    public function scopePending($q)    { return $q->where('status', 'pending'); }
    public function scopeDispatched($q) { return $q->where('status', 'dispatched'); }
    public function scopeDelivered($q)  { return $q->where('status', 'delivered'); }
}
