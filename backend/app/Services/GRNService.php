<?php

namespace App\Services;

use App\Models\GoodsReceivedNote;
use App\Models\GRNItem;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\StockLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GRNService extends BaseService
{
    protected JournalAutoPostService $journalService;

    public function __construct(JournalAutoPostService $journalService)
    {
        parent::__construct(new GoodsReceivedNote());
        $this->journalService = $journalService;
    }

    /**
     * Create a GRN from a Purchase Order
     */
    public function createFromPO(int $purchaseOrderId, array $data)
    {
        $purchaseOrder = PurchaseOrder::findOrFail($purchaseOrderId);

        if (!in_array($purchaseOrder->status, ['submitted', 'partial'])) {
            throw new \Exception('Purchase order must be submitted or partially received before creating a GRN.');
        }

        DB::beginTransaction();
        try {
            $grnYear = date('Y');
            $tenantId = auth()->user()->tenant_id;
            $nextGrnNum = DB::transaction(function () use ($grnYear, $tenantId) {
                $counter = DB::table('sequence_counters')
                    ->where('tenant_id', $tenantId)
                    ->where('type', 'grn')
                    ->where('year', $grnYear)
                    ->lockForUpdate()
                    ->first();
                $newVal = ($counter?->current_value ?? 0) + 1;
                DB::table('sequence_counters')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'type' => 'grn', 'year' => $grnYear],
                    ['current_value' => $newVal, 'updated_at' => now()]
                );
                return $newVal;
            });
            $grnNumber = 'GRN-' . $grnYear . '-' . str_pad($nextGrnNum, 4, '0', STR_PAD_LEFT);

            $grn = GoodsReceivedNote::create([
                'tenant_id'         => auth()->user()->tenant_id,
                'purchase_order_id' => $purchaseOrder->id,
                'warehouse_id'      => $purchaseOrder->warehouse_id,
                'grn_number'        => $grnNumber,
                'received_date'     => $data['received_date'] ?? now(),
                'status'            => 'completed',
                'notes'             => $data['notes'] ?? null,
                'created_by'        => auth()->id(),
            ]);

            foreach ($data['items'] as $item) {
                $orderItem = $purchaseOrder->items()->where('product_id', $item['product_id'])->first();
                if (!$orderItem) {
                    throw new \Exception("Product ID {$item['product_id']} not found in this Purchase Order.");
                }

                // Calculate already received quantity for this PO item
                $alreadyReceived = GRNItem::whereHas('grn', function ($query) use ($purchaseOrder) {
                    $query->where('purchase_order_id', $purchaseOrder->id)
                          ->where('status', 'completed');
                })->where('purchase_order_item_id', $orderItem->id)->sum('quantity_received');

                if (($alreadyReceived + $item['quantity_received']) > $orderItem->quantity) {
                    throw new \Exception("Received quantity for product {$orderItem->product->name} exceeds the ordered quantity.");
                }

                GRNItem::create([
                    'grn_id' => $grn->id,
                    'purchase_order_item_id' => $orderItem->id,
                    'product_id' => $item['product_id'],
                    'variant_id' => $orderItem->variant_id,
                    'unit_id' => $orderItem->unit_id,
                    'quantity_received' => $item['quantity_received'],
                ]);

                // Update PO item received quantity
                $orderItem->increment('received_quantity', $item['quantity_received']);

                // Update stock levels
                $product = $orderItem->product;
                
                // Find or create stock level for this warehouse
                $stockLevel = StockLevel::firstOrCreate([
                    'tenant_id' => auth()->user()->tenant_id,
                    'warehouse_id' => $purchaseOrder->warehouse_id,
                    'product_id' => $product->id,
                    'variant_id' => $orderItem->variant_id,
                ]);

                $quantityBefore = $stockLevel->quantity ?? 0;
                $newQtyIn = (float) $item['quantity_received'];
                $newUnitCost = (float) ($orderItem->unit_cost ?? 0);

                // Fix 6: recalculate Weighted Average Cost (WAC) BEFORE incrementing
                $existingQty  = (float) $stockLevel->quantity;
                $existingCost = (float) ($stockLevel->avg_cost ?? 0);
                if (($existingQty + $newQtyIn) > 0) {
                    $newAvgCost = (($existingQty * $existingCost) + ($newQtyIn * $newUnitCost))
                                  / ($existingQty + $newQtyIn);
                } else {
                    $newAvgCost = $newUnitCost;
                }

                $stockLevel->increment('quantity', $newQtyIn);
                $stockLevel->update(['avg_cost' => round($newAvgCost, 4)]);
                $quantityAfter = $stockLevel->fresh()->quantity;

                // Create stock movement record
                StockMovement::create([
                    'tenant_id'      => auth()->user()->tenant_id,
                    'warehouse_id'   => $purchaseOrder->warehouse_id,
                    'product_id'     => $product->id,
                    'variant_id'     => $orderItem->variant_id,
                    'unit_id'        => $orderItem->unit_id,
                    'reference_type' => 'GoodsReceivedNote',
                    'reference_id'   => $grn->id,
                    'type'           => 'in',
                    'quantity'       => $newQtyIn,
                    'quantity_before' => $quantityBefore,
                    'quantity_after'  => $quantityAfter,
                    'unit_cost'      => $newUnitCost,
                    'created_by'     => auth()->id(),
                ]);
            }

            // Update PO status
            $this->updatePOStatus($purchaseOrder);

            DB::commit();

            // Post-commit: auto-journal (non-fatal)
            try {
                $this->journalService->postGRN($grn);
            } catch (\Exception $e) {
                Log::warning('JournalAutoPost failed for GRN', ['grn_id' => $grn->id, 'error' => $e->getMessage()]);
            }

            return $grn->load(['items.product', 'warehouse']);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error creating GRN', [
                'error' => $e->getMessage(),
                'purchase_order_id' => $purchaseOrderId,
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Update Purchase Order status based on total received quantities
     */
    protected function updatePOStatus(PurchaseOrder $purchaseOrder)
    {
        $allItemsReceived = true;
        $anyItemReceived = false;

        foreach ($purchaseOrder->items as $item) {
            $totalReceived = GRNItem::whereHas('grn', function ($query) use ($purchaseOrder) {
                $query->where('purchase_order_id', $purchaseOrder->id)
                      ->where('status', 'completed');
            })->where('purchase_order_item_id', $item->id)->sum('quantity_received');

            if ($totalReceived < $item->quantity) {
                $allItemsReceived = false;
            }
            if ($totalReceived > 0) {
                $anyItemReceived = true;
            }
        }

        if ($allItemsReceived) {
            $purchaseOrder->update(['status' => 'received', 'received_date' => now()]);
        } elseif ($anyItemReceived) {
            $purchaseOrder->update(['status' => 'partial']);
        }
    }
}
