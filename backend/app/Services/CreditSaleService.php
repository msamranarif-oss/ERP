<?php

namespace App\Services;

use App\Exceptions\CreditSaleException;
use App\Exceptions\PaymentException;
use App\Models\CreditSale;
use App\Models\CreditSaleItem;
use App\Models\Installment;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use App\Services\LoggingService;
use App\Services\CachingService;

class CreditSaleService extends BaseService
{
    protected CreditSaleItemService $creditSaleItemService;
    protected InstallmentService $installmentService;

    public function __construct(
        CreditSaleItemService $creditSaleItemService,
        InstallmentService $installmentService
    ) {
        parent::__construct(new CreditSale());
        $this->creditSaleItemService = $creditSaleItemService;
        $this->installmentService = $installmentService;
    }

    public function getAll(array $filters = [], int $perPage = 15)
    {
        $cacheKey = 'credit_sales_list:' . md5(serialize($filters) . $perPage);
        
        return CachingService::remember($cacheKey, 15, function() use ($filters, $perPage) {
            $query = $this->model->with(['customer.customer', 'items.product', 'installments']);

            if (!empty($filters['search'])) {
                $query->where(function ($q) use ($filters) {
                    $q->where('credit_sale_number', 'like', '%' . $filters['search'] . '%')
                      ->orWhereHas('customer.customer', function ($sub) use ($filters) {
                          $sub->where('name', 'like', '%' . $filters['search'] . '%');
                      });
                });
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['customer_id'])) {
                $query->where('customer_id', $filters['customer_id']);
            }

            return $query->orderBy('created_at', 'desc')
                         ->paginate($perPage);
        }, ['credit_sales', 'lists']);
    }

    /**
     * Create a credit sale with items and installments
     */
    public function createCreditSale(array $data)
    {
        try {
            DB::beginTransaction();

            // Create credit sale
            $creditSale = CreditSale::create([
                'credit_sale_number' => 'CS-' . date('Y') . '-' . str_pad(CreditSale::whereYear('created_at', date('Y'))->count() +1, 4, '0', STR_PAD_LEFT),
                'sale_id' => $data['sale_id'] ?? null, // Add optional sale_id link
                'customer_id' => $data['customer_id'],
                'total_amount' => $data['total_amount'],
                'down_payment' => $data['down_payment'],
                'financed_amount' => $data['financed_amount'] ?? ($data['total_amount'] - $data['down_payment']),
                'interest_rate' => $data['interest_rate'] ?? 0,
                'interest_type' => $data['interest_type'] ?? 'simple',
                'interest_amount' => $data['interest_amount'] ?? 0,
                'total_payable' => $data['total_payable'] ?? ($data['total_amount'] - $data['down_payment']),
                'installment_count' => $data['installment_count'] ?? 1,
                'installment_frequency' => $data['installment_frequency'],
                'start_date' => $data['start_date'] ?? now(),
                'status' => 'active',
                'notes' => $data['notes'] ?? null,
                'tenant_id' => auth()->user()->tenant_id,
                'created_by' => auth()->id(),
            ]);

            // Create credit sale items
            foreach ($data['items'] as $item) {
                CreditSaleItem::create([
                    'credit_sale_id' => $creditSale->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'tax_percent' => $item['tax_percent'] ?? 0,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'subtotal' => $item['quantity'] * $item['unit_price'],
                    'tenant_id' => auth()->user()->tenant_id,
                ]);
            }

            // Create installments
            $this->generateInstallments($creditSale, $data);

            DB::commit();

            // Invalidate related caches
            CachingService::invalidateRelatedCaches('credit_sales_list');
            CachingService::invalidateRelatedCaches('customer', $data['customer_id']);

            // Log the creation for audit purposes
            LoggingService::audit('created', 'CreditSale', $creditSale->id, [
                'customer_id' => $data['customer_id'],
                'total_amount' => $data['total_amount'],
                'number_of_installments' => $data['number_of_installments'],
            ]);

            return $creditSale->load(['customer.customer', 'items.product', 'installments']);
        } catch (\Exception $e) {
            DB::rollBack();
            LoggingService::error('Error creating credit sale', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            
            throw $e;
        }
    }

    /**
     * Record payment for a credit sale installment
     */
    public function recordPayment(int $creditSaleId, array $data)
    {
        try {
            DB::beginTransaction();

            $creditSale = CreditSale::findOrFail($creditSaleId);
            $installment = Installment::find($data['installment_id']);

            if (!$installment || $installment->credit_sale_id !== $creditSale->id) {
                throw PaymentException::dueToInvalidInstallment();
            }

            if ($installment->status !== 'pending') {
                throw PaymentException::dueToAlreadyPaid();
            }

            $remainingAmount = $installment->getRemainingAmountAttribute();
            if ($data['amount'] > $remainingAmount) {
                throw PaymentException::dueToExceedingInstallmentBalance($data['amount'], $remainingAmount);
            }

            // Create payment record
            $payment = Payment::create([
                'payment_number' => 'PMT-' . date('Y') . '-' . str_pad(Payment::whereYear('created_at', date('Y'))->count() + 1, 4, '0', STR_PAD_LEFT),
                'credit_sale_id' => $creditSale->id,
                'installment_id' => $data['installment_id'],
                'payment_method_id' => $data['payment_method_id'],
                'amount' => $data['amount'],
                'status' => 'completed',
                'tenant_id' => auth()->user()->tenant_id,
                'created_by' => auth()->id(),
            ]);

            // Update installment
            $installment->update([
                'paid_amount' => $installment->paid_amount + $data['amount'],
                'status' => $installment->getRemainingAmountAttribute() <= 0 ? 'paid' : 'partial',
            ]);

            // Update credit sale status if all installments are paid
            if ($creditSale->installments()->where('status', '!=', 'paid')->count() === 0) {
                $creditSale->update(['status' => 'completed']);
            }

            DB::commit();

            // Log the payment for audit purposes
            LoggingService::audit('payment_recorded', 'CreditSale', $creditSale->id, [
                'installment_id' => $data['installment_id'],
                'amount' => $data['amount'],
                'payment_method_id' => $data['payment_method_id'],
            ]);

            return $payment;
        } catch (PaymentException $e) {
            DB::rollBack();
            LoggingService::error('Payment processing error', [
                'error' => $e->getMessage(),
                'credit_sale_id' => $creditSaleId,
                'data' => $data
            ]);
            
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            LoggingService::error('Unexpected error recording payment', [
                'error' => $e->getMessage(),
                'credit_sale_id' => $creditSaleId,
                'data' => $data
            ]);
            
            throw $e;
        }
    }

    /**
     * Delete a credit sale with related records
     */
    public function deleteCreditSale(int $creditSaleId)
    {
        try {
            DB::beginTransaction();

            $creditSale = CreditSale::findOrFail($creditSaleId);

            if ($creditSale->status !== 'pending' && $creditSale->installments()->where('status', 'pending')->count() > 0) {
                throw CreditSaleException::dueToAlreadyCompleted();
            }

            // Delete related records
            $creditSale->items()->delete();
            $creditSale->installments()->delete();
            $creditSale->delete();

            DB::commit();

            return true;
        } catch (CreditSaleException $e) {
            DB::rollBack();
            LoggingService::error('Credit sale deletion error', [
                'error' => $e->getMessage(),
                'credit_sale_id' => $creditSaleId
            ]);
            
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            LoggingService::error('Unexpected error deleting credit sale', [
                'error' => $e->getMessage(),
                'credit_sale_id' => $creditSaleId
            ]);
            
            throw $e;
        }
    }

    /**
     * Get credit sale with schedule (installments)
     */
    public function getSchedule(int $creditSaleId)
    {
        $creditSale = CreditSale::findOrFail($creditSaleId);
        return $creditSale->installments()->orderBy('due_date')->get();
    }

    /**
     * Generate installments for a credit sale
     */
    private function generateInstallments($creditSale, array $data)
    {
        $principalPerInstallment = $creditSale->loan_amount / $creditSale->number_of_installments;
        $interestPerInstallment = ($creditSale->loan_amount * ($creditSale->interest_rate / 100)) / $creditSale->number_of_installments;
        $amountPerInstallment = $principalPerInstallment + $interestPerInstallment;

        $currentDate = new \DateTime($data['first_installment_date']);
        $frequencyInterval = $this->getFrequencyInterval($data['installment_frequency']);

        for ($i = 1; $i <= $data['number_of_installments']; $i++) {
            Installment::create([
                'credit_sale_id' => $creditSale->id,
                'installment_number' => $i,
                'due_date' => $currentDate->format('Y-m-d'),
                'principal_amount' => $principalPerInstallment,
                'interest_amount' => $interestPerInstallment,
                'total_amount' => $amountPerInstallment,
                'paid_amount' => 0,
                'remaining_amount' => $amountPerInstallment,
                'status' => 'pending',
                'tenant_id' => auth()->user()->tenant_id,
            ]);

            // Move to next installment date based on frequency
            $currentDate->modify($frequencyInterval);
        }
    }

    /**
     * Get frequency interval for installment generation
     */
    private function getFrequencyInterval(string $frequency): string
    {
        switch ($frequency) {
            case 'weekly':
                return '+1 week';
            case 'biweekly':
                return '+2 weeks';
            case 'monthly':
                return '+1 month';
            case 'quarterly':
                return '+3 months';
            default:
                return '+1 month';
        }
    }
}