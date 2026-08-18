<?php

namespace App\Services;

use App\Models\Installment;
use App\Models\CreditSale;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InstallmentService extends BaseService
{
    public function __construct()
    {
        parent::__construct(new Installment());
    }

    /**
     * Get overdue installments
     */
    public function getOverdueInstallments()
    {
        $today = now()->toDateString();
        return Installment::where('due_date', '<', $today)
            ->where('status', 'pending')
            ->whereHas('creditSale', function ($query) {
                $query->where('status', 'active');
            })
            ->with(['creditSale.customer.customer'])
            ->orderBy('due_date', 'asc')
            ->get();
    }

    /**
     * Get installments due today
     */
    public function getDueTodayInstallments()
    {
        $today = now()->toDateString();
        return Installment::where('due_date', $today)
            ->where('status', 'pending')
            ->whereHas('creditSale', function ($query) {
                $query->where('status', 'active');
            })
            ->with(['creditSale.customer.customer'])
            ->get();
    }

    /**
     * Get upcoming installments
     */
    public function getUpcomingInstallments($days = 7)
    {
        $today = now()->toDateString();
        $nextWeek = now()->addDays($days)->toDateString();
        
        return Installment::whereBetween('due_date', [$today, $nextWeek])
            ->where('status', 'pending')
            ->whereHas('creditSale', function ($query) {
                $query->where('status', 'active');
            })
            ->with(['creditSale.customer.customer'])
            ->orderBy('due_date', 'asc')
            ->get();
    }

    /**
     * Process installment payment
     */
    public function processPayment(int $installmentId, array $data)
    {
        $installment = Installment::findOrFail($installmentId);
        
        if ($installment->status !== 'pending') {
            throw new \Exception('Installment is not pending.');
        }

        $creditSale = $installment->creditSale;

        if ($creditSale->tenant_id !== auth()->user()->tenant_id) {
            throw new \Exception('Unauthorized access.');
        }

        DB::beginTransaction();
        try {
            // Create payment record
            $payment = Payment::create([
                'payment_number' => 'PMT-' . date('Y') . '-' . str_pad(Payment::whereYear('created_at', date('Y'))->count() + 1, 4, '0', STR_PAD_LEFT),
                'credit_sale_id' => $creditSale->id,
                'installment_id' => $installment->id,
                'payment_method_id' => $data['payment_method_id'],
                'amount' => $data['amount'],
                'status' => 'completed',
                'tenant_id' => auth()->user()->tenant_id,
                'created_by' => auth()->id(),
            ]);

            // Update installment
            $newPaidAmount = $installment->paid_amount + $data['amount'];
            $newRemainingAmount = $installment->amount - $newPaidAmount;
            
            $installment->update([
                'paid_amount' => $newPaidAmount,
                'remaining_amount' => max(0, $newRemainingAmount),
                'balance' => max(0, $newRemainingAmount), // Keep balance in sync
                'status' => ($newRemainingAmount <= 0) ? 'paid' : 'partial',
            ]);

            // Update credit sale status if all installments are paid
            if ($creditSale->installments()->where('status', '!=', 'paid')->count() === 0) {
                $creditSale->update(['status' => 'completed']);
            }

            DB::commit();

            return $payment;
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error processing installment payment', [
                'error' => $e->getMessage(),
                'installment_id' => $installmentId,
                'data' => $data
            ]);
            
            throw $e;
        }
    }

    /**
     * Get installments with filters
     */
    public function getInstallmentsWithFilters(array $filters = [], int $perPage = 15)
    {
        $query = Installment::with(['creditSale.customer.customer']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereHas('creditSale.creditSaleNumber', 'like', '%' . $filters['search'] . '%')
                  ->orWhereHas('creditSale.customer.customer.name', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['customer_id'])) {
            $query->whereHas('creditSale', function ($sub) use ($filters) {
                $sub->where('customer_id', $filters['customer_id']);
            });
        }

        return $query->orderBy('due_date', 'asc')
                     ->paginate($perPage);
    }
}