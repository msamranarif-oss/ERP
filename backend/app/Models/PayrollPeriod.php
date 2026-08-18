<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollPeriod extends Model
{
    protected $fillable = ['tenant_id','name','period_type','start_date','end_date','status','approved_by','approved_at','journal_entry_id','notes'];
    protected $casts    = ['start_date' => 'date', 'end_date' => 'date', 'approved_at' => 'datetime'];

    public function scopeTenant($q) { return $q->where('tenant_id', auth()->user()?->tenant_id); }
    public function runs(): HasMany         { return $this->hasMany(PayrollRun::class); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }

    public function getTotalGrossAttribute(): float { return $this->runs->sum('gross_earnings'); }
    public function getTotalNetAttribute(): float   { return $this->runs->sum('net_pay'); }
    public function getTotalTaxAttribute(): float   { return $this->runs->sum('tax_amount'); }
}
