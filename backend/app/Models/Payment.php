<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivityForTenant;

class Payment extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes, LogsActivityForTenant;

    protected $table = 'installment_payments';

    protected $fillable = [
        'tenant_id',
        'payment_number',
        'credit_sale_id',
        'installment_schedule_id',
        'payment_method_id',
        'amount',
        'principal_paid',
        'interest_paid',
        'penalty_paid',
        'status',
        'notes',
        'reference',
        'received_by',
        'payment_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->payment_number)) {
                $tenantId = $model->tenant_id ?: (auth()->user() ? auth()->user()->tenant_id : null);
                $model->payment_number = 'PMT-' . $tenantId . '-' . date('Y') . '-' . str_pad(
                    static::where('tenant_id', $tenantId)
                        ->whereYear('created_at', date('Y'))
                        ->count() + 1, 4, '0', STR_PAD_LEFT
                );
            }
        });
    }

    public function creditSale(): BelongsTo
    {
        return $this->belongsTo(CreditSale::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class, 'installment_schedule_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}