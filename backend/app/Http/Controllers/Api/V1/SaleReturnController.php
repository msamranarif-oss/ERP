<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Services\JournalAutoPostService;
use App\Services\SequenceService;
use App\Services\CreditSaleService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleReturnController extends ApiController
{
    protected SequenceService $sequenceService;
    protected CreditSaleService $creditSaleService;
    protected JournalAutoPostService $autoPostService;

    public function __construct(
        SequenceService $sequenceService,
        CreditSaleService $creditSaleService,
        JournalAutoPostService $autoPostService
    ) {
        $this->sequenceService  = $sequenceService;
        $this->creditSaleService = $creditSaleService;
        $this->autoPostService  = $autoPostService;
        // Authorization on all methods
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', SaleReturn::class);

        $query = SaleReturn::with(['sale', 'items.product'])
            ->where('tenant_id', auth()->user()->tenant_id);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('return_number', 'like', '%' . $request->search . '%')
                  ->orWhereHas('sale', function ($sub) use ($request) {
                      $sub->where('sale_number', 'like', '%' . $request->search . '%');
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('sale_id')) {
            $query->where('sale_id', $request->sale_id);
        }

        $returns = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        return $this->successResponse($returns);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', SaleReturn::class);

        $validated = $request->validate([
            'sale_id'                   => 'required|exists:sales,id',
            'reason'                    => 'required|string|max:255',
            'items'                     => 'required|array|min:1',
            'items.*.sale_item_id'      => 'required|exists:sale_items,id',
            'items.*.product_id'        => 'required|exists:products,id',
            'items.*.quantity'          => 'required|numeric|min:0.0001',
            'items.*.unit_price'        => 'required|numeric|min:0',
            'items.*.condition'         => 'nullable|in:good,damaged,defective',
            'items.*.return_to_stock'   => 'nullable|boolean',
            'items.*.notes'             => 'nullable|string|max:500',
            'refund_method'             => 'required|in:cash,bank_transfer,credit_account',
            'notes'                     => 'nullable|string|max:1000',
        ]);

        /** @var Sale $sale */
        $sale = Sale::where('id', $validated['sale_id'])
            ->where('tenant_id', auth()->user()->tenant_id)
            ->first();

        if (!$sale) {
            return $this->errorResponse('Sale not found.', 404);
        }

        if ($sale->status === 'voided') {
            return $this->errorResponse('Cannot return a voided sale.', 422, [], 'VOIDED_SALE_RETURN');
        }

        // Task 8: Validate return quantities do not exceed original sale item quantities
        foreach ($validated['items'] as $item) {
            $saleItem = \App\Models\SaleItem::where('id', $item['sale_item_id'])
                ->where('sale_id', $sale->id)
                ->first();

            if (!$saleItem) {
                return $this->errorResponse(
                    "Sale item #{$item['sale_item_id']} does not belong to sale #{$sale->id}.",
                    422
                );
            }

            // Calculate already-returned quantity for this sale item
            $alreadyReturned = \App\Models\SaleReturnItem::whereHas('saleReturn', function ($q) use ($sale) {
                    $q->where('sale_id', $sale->id)->where('status', 'completed');
                })
                ->where('sale_item_id', $saleItem->id)
                ->sum('quantity');

            $availableToReturn = $saleItem->quantity - $alreadyReturned;

            if ((float) $item['quantity'] > $availableToReturn) {
                return $this->errorResponse(
                    "Cannot return {$item['quantity']} of '{$saleItem->product_name}'. " .
                    "Originally sold: {$saleItem->quantity}, already returned: {$alreadyReturned}, " .
                    "available to return: {$availableToReturn}.",
                    422
                );
            }
        }

        DB::beginTransaction();
        try {
            // Fix 14: Use SequenceService for sequential return numbers
            $returnNumber = $this->sequenceService->generateReference('sale_return', 'SR');

            // Compute totals from items, including tax from original sale items
            $refundTotal = 0;
            $taxTotal = 0;
            foreach ($validated['items'] as $item) {
                $lineAmount = (float)$item['quantity'] * (float)$item['unit_price'];
                $refundTotal += $lineAmount;

                // Proportional tax: use the original sale item's tax rate
                $saleItem = \App\Models\SaleItem::find($item['sale_item_id']);
                if ($saleItem && $saleItem->tax_rate > 0) {
                    $taxTotal += $lineAmount * ((float) $saleItem->tax_rate / 100);
                }
            }
            $taxTotal = round($taxTotal, 2);

            $saleReturn = SaleReturn::create([
                'return_number'  => $returnNumber,
                'tenant_id'      => auth()->user()->tenant_id,
                'sale_id'        => $validated['sale_id'],
                'branch_id'      => $sale->branch_id,
                'warehouse_id'   => $sale->warehouse_id,
                'return_date'    => now()->toDateString(),
                'reason'         => $validated['reason'],
                'subtotal'       => $refundTotal,
                'tax_amount'     => $taxTotal,
                'total'          => round($refundTotal + $taxTotal, 2),
                'refund_method'  => $validated['refund_method'],
                'notes'          => $validated['notes'] ?? null,
                'status'         => 'completed',
                'processed_by'   => auth()->id(),
            ]);

            // Fix 5: Correct SaleReturnItem fields + stock restoration
            foreach ($validated['items'] as $item) {
                $amount = (float)$item['quantity'] * (float)$item['unit_price'];

                SaleReturnItem::create([
                    'sale_return_id' => $saleReturn->id,
                    'sale_item_id'   => $item['sale_item_id'],  // Required FK
                    'product_id'     => $item['product_id'],
                    'quantity'       => $item['quantity'],
                    'unit_price'     => $item['unit_price'],
                    'amount'         => $amount,               // Required column
                    'condition'      => $item['condition'] ?? 'good',
                    'return_to_stock' => $item['return_to_stock'] ?? true,
                    'notes'          => $item['notes'] ?? null,
                ]);

                // Fix 5: Restore stock levels if return_to_stock is true
                if ($item['return_to_stock'] ?? true) {
                    // Fetch original sale item to get batch, variant, and multi-unit info
                    $saleItem = \App\Models\SaleItem::find($item['sale_item_id']);
                    $batchId = $saleItem->batch_id ?? null;
                    $variantId = $saleItem->variant_id ?? null;
                    
                    // Multi-unit selling fix: Always restore the base quantity physically back to stock
                    $conversionFactor = (float) ($saleItem->conversion_factor ?? 1);
                    $baseQuantityToReturn = (float) $item['quantity'] * $conversionFactor;

                    $stockLevel = StockLevel::firstOrCreate([
                        'tenant_id'    => auth()->user()->tenant_id,
                        'warehouse_id' => $sale->warehouse_id,
                        'product_id'   => $item['product_id'],
                        'variant_id'   => $variantId,
                        'batch_id'     => $batchId,
                    ]);

                    $quantityBefore = $stockLevel->quantity ?? 0;
                    $stockLevel->increment('quantity', $baseQuantityToReturn);
                    $quantityAfter  = $stockLevel->fresh()->quantity;

                    // Create stock movement record for audit trail
                    StockMovement::create([
                        'tenant_id'       => auth()->user()->tenant_id,
                        'warehouse_id'    => $sale->warehouse_id,
                        'product_id'      => $item['product_id'],
                        'variant_id'      => $variantId,
                        'batch_id'        => $batchId,
                        'reference_type'  => 'SaleReturn',
                        'reference_id'    => $saleReturn->id,
                        'type'            => 'in',
                        'quantity'        => $baseQuantityToReturn,
                        'quantity_before' => $quantityBefore,
                        'quantity_after'  => $quantityAfter,
                        'unit_cost'       => $item['unit_price'] / $conversionFactor, // base unit cost
                        'created_by'      => auth()->id(),
                    ]);

                    // If batch managed, optionally restore batch remaining quantity
                    if ($batchId) {
                        $batch = \App\Models\Batch::find($batchId);
                        if ($batch) {
                            $batch->increment('quantity_remaining', $baseQuantityToReturn);
                        }
                    }

                    // Task 7: Reset serial number status from 'sold' back to 'returned'
                    // so it is no longer sellable until explicitly re-stocked
                    if (!empty($saleItem->serial_number_id)) {
                        $serial = \App\Models\SerialNumber::find($saleItem->serial_number_id);
                        if ($serial) {
                            $condition = $item['condition'] ?? 'good';
                            // Mark defective/damaged serials appropriately; good condition → returned
                            $newSerialStatus = in_array($condition, ['damaged', 'defective']) ? 'defective' : 'returned';
                            $serial->update([
                                'status'       => $newSerialStatus,
                                'sale_item_id' => null,
                                'sold_to'      => null,
                                'sold_at'      => null,
                            ]);
                        }
                    }
                }
            }

            // Update the original sale's balance
            if ($sale->balance_due > 0) {
                // Determine how much of the return applies to the outstanding balance
                $balanceReduction = min($sale->balance_due, $refundTotal);
                $sale->decrement('balance_due', $balanceReduction);
            }

            // If this is a credit sale, apply the return to reduce outstanding installments
            if ($sale->creditSale) {
                $this->creditSaleService->applyReturn($sale->creditSale->id, $refundTotal);
            }

            // Adjust payment status if fully paid after return
            if ($sale->fresh()->balance_due <= 0 && $sale->payment_status !== 'paid') {
                $sale->update(['payment_status' => 'paid']);
            } elseif ($sale->payment_status === 'paid' && $refundTotal > 0 && $validated['refund_method'] === 'credit_account') {
                // If they paid full but return to credit account, it leaves a negative balance due or partial
                // Keeping as paid for now depending on accounting logic
            }

            DB::commit();

            // Task 6: Post sale-return journal entry (non-fatal — cashier flow must not be blocked)
            try {
                $this->autoPostService->postSaleReturn($saleReturn);
            } catch (\Exception $e) {
                Log::warning('postSaleReturn failed', [
                    'return_id' => $saleReturn->id,
                    'error'     => $e->getMessage(),
                ]);
            }

            return $this->successResponse(
                $saleReturn->load(['sale', 'items.product']),
                'Sale return processed successfully.',
                201
            );
        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Failed to process sale return: ' . $e->getMessage(), 500);
        }
    }

    public function show(SaleReturn $sale_return): JsonResponse
    {
        $this->authorize('view', $sale_return);

        return $this->successResponse($sale_return->load(['sale', 'items.product']));
    }

    public function update(Request $request, SaleReturn $sale_return): JsonResponse
    {
        $this->authorize('update', $sale_return);

        if ($sale_return->status !== 'pending') {
            return $this->errorResponse('Only pending returns can be updated.', 422);
        }

        $validated = $request->validate([
            'reason'        => 'sometimes|required|string|max:255',
            'refund_method' => 'sometimes|required|in:cash,bank_transfer,credit_account',
            'notes'         => 'sometimes|nullable|string|max:1000',
        ]);

        $sale_return->update($validated);

        return $this->successResponse(
            $sale_return->load(['sale', 'items.product']),
            'Sale return updated successfully.'
        );
    }

    public function destroy(SaleReturn $sale_return): JsonResponse
    {
        $this->authorize('delete', $sale_return);

        if ($sale_return->status !== 'pending') {
            return $this->errorResponse('Cannot delete completed or processed sale return.', 422);
        }

        $sale_return->items()->delete();
        $sale_return->delete();

        return $this->successResponse(null, 'Sale return deleted successfully.');
    }
}