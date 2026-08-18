<?php

namespace App\Services;

use App\Models\PurchaseBill;
use App\Models\PurchaseBillItem;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseBillService extends BaseService
{
    protected JournalAutoPostService $autoPostService;

    public function __construct(JournalAutoPostService $autoPostService)
    {
        parent::__construct(new PurchaseBill);
        $this->autoPostService = $autoPostService;
    }

    /**
     * Create a Purchase Bill from a Purchase Order
     */
    public function createFromPO(int $purchaseOrderId, array $data)
    {
        $purchaseOrder = PurchaseOrder::findOrFail($purchaseOrderId);

        DB::beginTransaction();
        try {
            $bill = PurchaseBill::create([
                'tenant_id' => auth()->user()->tenant_id,
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_id' => $purchaseOrder->supplier_id,
                'bill_number' => $data['bill_number'] ?? 'BILL-'.date('Y').'-'.str_pad(PurchaseBill::whereYear('created_at', date('Y'))->count() + 1, 4, '0', STR_PAD_LEFT),
                'bill_date' => $data['bill_date'] ?? now(),
                'due_date' => $data['due_date'] ?? now()->addDays(30),
                'subtotal' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'shipping_cost' => $data['shipping_cost'] ?? 0,
                'total' => 0,
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $subtotal = 0;
            $taxAmount = 0;

            foreach ($purchaseOrder->items as $item) {
                $receivedQty = $item->received_quantity_total;
                if ($receivedQty <= 0) {
                    continue;
                }

                $itemTax = ($item->tax / $item->quantity) * $receivedQty;
                $itemTotal = ($item->unit_price * $receivedQty) + $itemTax;

                PurchaseBillItem::create([
                    'purchase_bill_id' => $bill->id,
                    'purchase_order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'unit_id' => $item->unit_id,
                    'quantity' => $receivedQty,
                    'unit_price' => $item->unit_price,
                    'tax' => $itemTax,
                    'total' => $itemTotal,
                ]);

                $subtotal += ($item->unit_price * $receivedQty);
                $taxAmount += $itemTax;
            }

            $bill->update([
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $subtotal + $taxAmount + $bill->shipping_cost,
            ]);

            DB::commit();

            return $bill->load(['items.product', 'supplier']);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error creating Purchase Bill', [
                'error' => $e->getMessage(),
                'purchase_order_id' => $purchaseOrderId,
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Approve a purchase bill and post to general ledger (GRNI + AP + Input VAT + PPV).
     */
    public function approve(int $billId, ?int $approvedBy = null): PurchaseBill
    {
        $bill = PurchaseBill::findOrFail($billId);

        if ($bill->status === 'approved' || $bill->status === 'paid' || $bill->status === 'partial') {
            throw new \RuntimeException('Purchase Bill is already approved/paid.');
        }

        DB::beginTransaction();
        try {
            $bill->update([
                'status' => 'approved',
            ]);

            DB::commit();

            try {
                $this->autoPostService->postPurchaseBill($bill);
            } catch (\Throwable $e) {
                Log::error('postPurchaseBill failed after commit', [
                    'bill_id' => $bill->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            return $bill->fresh()->load(['items.product', 'supplier']);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error approving Purchase Bill', [
                'error' => $e->getMessage(),
                'bill_id' => $billId,
            ]);
            throw $e;
        }
    }

    /**
     * Process a payment against a bill and post AP settlement JE.
     */
    public function processPayment(
        int $billId,
        float $amount,
        ?int $bankAccountId = null,
        ?string $paymentMethod = null,
        ?string $reference = null
    ) {
        $bill = PurchaseBill::findOrFail($billId);

        if ($bill->status === 'pending') {
            throw new \RuntimeException('Purchase Bill must be approved before payment.');
        }

        DB::beginTransaction();
        try {
            $bill->increment('paid_amount', $amount);

            if (bccomp((string) $bill->paid_amount, (string) $bill->total, 4) >= 0) {
                $bill->update(['status' => 'paid']);
            } elseif ($bill->paid_amount > 0) {
                $bill->update(['status' => 'partial']);
            }

            DB::commit();

            try {
                $this->autoPostService->postPurchaseBillPayment(
                    bill: $bill,
                    amountPaid: $amount,
                    bankAccountId: $bankAccountId
                );
            } catch (\Throwable $e) {
                Log::error('postPurchaseBillPayment failed after commit', [
                    'bill_id' => $bill->id,
                    'amount' => $amount,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            return $bill->fresh();
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error processing bill payment', [
                'error' => $e->getMessage(),
                'bill_id' => $billId,
                'amount' => $amount,
            ]);
            throw $e;
        }
    }
}
