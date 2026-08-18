<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Installment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService extends BaseService
{
    public function __construct()
    {
        parent::__construct(new Payment());
    }

    /**
     * Create a payment and update related records
     */
    public function createPayment(array $data)
    {
        try {
            DB::beginTransaction();

            $payment = Payment::create([
                'credit_sale_id' => $data['credit_sale_id'],
                'installment_id' => $data['installment_id'] ?? null,
                'payment_method_id' => $data['payment_method_id'],
                'amount' => $data['amount'],
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'status' => 'completed',
                'created_by' => $data['created_by'] ?? auth()->id(),
            ]);

            // Update installment with payment information if installment_id is provided
            if (!empty($data['installment_id'])) {
                $installment = Installment::findOrFail($data['installment_id']);
                $installment->update([
                    'paid_amount' => $installment->paid_amount + $data['amount'],
                    'status' => $installment->getRemainingAmountAttribute() <= 0 ? 'paid' : 'partial',
                ]);

                // Update credit sale status if all installments are paid
                $creditSale = $payment->creditSale;
                if ($creditSale->installments()->where('status', '!=', 'paid')->count() === 0) {
                    $creditSale->update(['status' => 'completed']);
                }
            }

            DB::commit();

            return $payment->load(['creditSale', 'installment', 'paymentMethod', 'createdBy']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating payment', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            
            throw $e;
        }
    }

    /**
     * Get payments with filters
     */
    public function getPaymentsWithFilters(array $filters = [], int $perPage = 15)
    {
        $query = Payment::with(['creditSale', 'installment', 'paymentMethod', 'createdBy']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('payment_number', 'like', '%' . $filters['search'] . '%')
                  ->orWhereHas('creditSale', function ($sub) use ($filters) {
                      $sub->whereHas('customer.customer', function ($subSub) use ($filters) {
                          $subSub->where('name', 'like', '%' . $filters['search'] . '%');
                      });
                  });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['credit_sale_id'])) {
            $query->where('credit_sale_id', $filters['credit_sale_id']);
        }

        if (!empty($filters['installment_id'])) {
            $query->where('installment_id', $filters['installment_id']);
        }

        return $query->orderBy('created_at', 'desc')
                     ->paginate($perPage ?? 15);
    }
}