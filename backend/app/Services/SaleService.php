<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\Sale;
use App\Models\Payment;
use App\Models\StockLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleService extends BaseService
{
    protected CreditSaleService $creditSaleService;
    protected JournalEntryService $journalEntryService;

    public function __construct(CreditSaleService $creditSaleService, JournalEntryService $journalEntryService)
    {
        parent::__construct(new Sale());
        $this->creditSaleService = $creditSaleService;
        $this->journalEntryService = $journalEntryService;
    }

    /**
     * Void a completed sale
     */
    public function voidSale(int $saleId, array $data)
    {
        $sale = Sale::findOrFail($saleId);

        if ($sale->status !== 'completed') {
            throw new \Exception('Cannot void sale that is not completed.');
        }

        DB::beginTransaction();
        try {
            // Update sale status
            $sale->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => auth()->id(),
                'void_reason' => $data['reason'],
            ]);

            // Void payments
            $sale->payments()->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => auth()->id(),
            ]);

            // Restore stock
            foreach ($sale->items as $item) {
                $product = $item->product;
                if ($product) {
                    $batchId = $item->batch_id;

                    // Restore batch quantity if applicable
                    if ($batchId) {
                        $batch = \App\Models\Batch::find($batchId);
                        if ($batch) {
                            // Task 3 fix: restore base_quantity (physical units), not selling-unit quantity
                            $restoreQty = (float) ($item->base_quantity ?? $item->quantity);
                            $batch->increment('quantity_remaining', $restoreQty);
                        }
                    }

                    // Reset serial status if applicable
                    if ($item->serial_number_id) {
                        $serial = \App\Models\SerialNumber::find($item->serial_number_id);
                        if ($serial) {
                            $serial->update([
                                'status' => 'in_stock',
                                'sale_item_id' => null,
                                'sold_to' => null,
                                'sold_at' => null,
                            ]);
                        }
                    }

                    // Find or create stock level for this warehouse (optionally by batch)
                    $stockLevel = StockLevel::firstOrCreate([
                        'tenant_id' => auth()->user()->tenant_id,
                        'warehouse_id' => $sale->warehouse_id,
                        'product_id' => $product->id,
                        'batch_id' => $batchId,
                    ]);

                    // Task 3 fix: always restore the physical base-unit quantity, not the
                    // selling-unit quantity. For a sale of "2 dozen" (base_qty=24), this
                    // restores 24 units to stock, not 2.
                    $restoreQty = (float) ($item->base_quantity ?? $item->quantity);
                    $quantityBefore = $stockLevel->quantity ?? 0;
                    $stockLevel->increment('quantity', $restoreQty);
                    $quantityAfter = $stockLevel->fresh()->quantity;

                    // Create stock movement record
                    \App\Models\StockMovement::create([
                        'tenant_id' => auth()->user()->tenant_id,
                        'warehouse_id' => $sale->warehouse_id,
                        'product_id' => $product->id,
                        'batch_id' => $batchId,
                        'reference_type' => 'SaleVoid',
                        'reference_id' => $sale->id,
                        'type' => 'in',
                        'quantity' => $restoreQty,
                        'quantity_before' => $quantityBefore,
                        'quantity_after' => $quantityAfter,
                        'unit_cost' => $product->cost_price ?? 0,
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            // If there's an associated credit sale, cancel it too
            if ($sale->creditSale) {
                $this->creditSaleService->voidCreditSale($sale->creditSale->id);
            }

            // Task 2 fix: look up the JE by reference_type + reference_id, not by the
            // 'SALE-{id}' string (which never matched the stored 'SALE-{sale_number}').
            $journalEntry = JournalEntry::where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->where('status', 'posted')
                ->first();

            if ($journalEntry) {
                $this->journalEntryService->voidJournalEntry($journalEntry->id, [
                    'reason' => 'Sale Voided: '.($data['reason'] ?? 'N/A'),
                ]);
                // Mark accounting as resolved after successful reversal
                $sale->update(['accounting_status' => 'posted']);
            } else {
                Log::warning('voidSale: no posted journal entry found for sale; skipping JE reversal.', [
                    'sale_id' => $sale->id,
                ]);
            }

            DB::commit();

            return $sale->load(['customer', 'items.product', 'payments.paymentMethod']);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error voiding sale', [
                'error' => $e->getMessage(),
                'sale_id' => $saleId,
                'data' => $data
            ]);
            
            throw $e;
        }
    }

    /**
     * Generate receipt data for a sale
     */
    public function generateReceipt(int $saleId)
    {
        $sale = Sale::with(['customer', 'items.product', 'payments.paymentMethod'])->findOrFail($saleId);

        return [
            'sale_number'     => $sale->sale_number,
            'date'            => $sale->created_at->format('Y-m-d H:i:s'),
            'customer'        => $sale->customer ? [
                'name'  => $sale->customer->name,
                'phone' => $sale->customer->phone,
                'email' => $sale->customer->email,
            ] : null,
            'items'           => $sale->items->map(function ($item) {
                return [
                    'product_name' => $item->product_name ?? ($item->product?->name ?? ''),
                    'quantity'     => $item->quantity,
                    'unit_price'   => $item->unit_price,
                    'discount'     => $item->discount,
                    'tax'          => $item->tax,
                    'subtotal'     => $item->total,  // sale_items column is 'total'
                ];
            }),
            'subtotal'        => $sale->subtotal,
            'discount_amount' => $sale->discount_amount,
            'tax_amount'      => $sale->tax_amount,
            'shipping_amount' => $sale->shipping_amount,
            'total'           => $sale->total,
            'paid_amount'     => $sale->paid_amount,
            'change_amount'   => $sale->change_amount,
            'balance_due'     => $sale->balance_due,
            'payment_status'  => $sale->payment_status,
            'payments'        => $sale->payments->map(function ($payment) {
                return [
                    'method' => $payment->paymentMethod?->name ?? 'Unknown',
                    'amount' => $payment->amount,
                ];
            }),
        ];
    }

    /**
     * Get sales with filters
     */
    public function getSalesWithFilters(array $filters = [], int $perPage = 15)
    {
        $query = Sale::with(['customer', 'items.product', 'payments.paymentMethod', 'registerSession', 'branch']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('sale_number', 'like', '%' . $filters['search'] . '%')
                  ->orWhereHas('customer', function ($sub) use ($filters) {
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

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('created_at', 'desc')
                     ->paginate($perPage);
    }
}