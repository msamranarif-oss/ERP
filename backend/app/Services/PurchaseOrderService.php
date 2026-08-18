<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseOrderService extends BaseService
{
    public function __construct()
    {
        parent::__construct(new PurchaseOrder());
    }

    /**
     * Create a new purchase order
     */
    public function createPurchaseOrder(array $data)
    {
        DB::beginTransaction();
        try {
            $subtotal = 0;
            $taxAmount = 0;

            // Generate PO Number
            $poNumber = 'PO-' . date('Y') . '-' . str_pad(PurchaseOrder::whereYear('created_at', date('Y'))->count() + 1, 4, '0', STR_PAD_LEFT);

            $purchaseOrder = PurchaseOrder::create([
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'po_number' => $poNumber,
                'order_date' => $data['date'],
                'expected_date' => $data['expected_delivery_date'],
                'notes' => $data['notes'] ?? null,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'shipping_cost' => $data['shipping_cost'] ?? 0,
                'status' => 'pending',
                'tenant_id' => auth()->user()->tenant_id,
                'created_by' => auth()->id(),
            ]);

            foreach ($data['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                $itemPrice = $item['unit_cost'];
                $itemQty = $item['quantity'];
                $itemTaxRate = $item['tax_rate'] ?? 0;
                
                $itemSubtotal = $itemPrice * $itemQty;
                $itemTax = $itemSubtotal * ($itemTaxRate / 100);
                $itemTotal = $itemSubtotal + $itemTax;

                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $product->id,
                    'unit_id' => $product->base_unit_id,
                    'quantity' => $itemQty,
                    'unit_price' => $itemPrice,
                    'tax' => $itemTax,
                    'total' => $itemTotal,
                ]);

                $subtotal += $itemSubtotal;
                $taxAmount += $itemTax;
            }

            // Update PO with calculated totals
            $total = $subtotal + $taxAmount + ($data['shipping_cost'] ?? 0) - ($data['discount_amount'] ?? 0);
            $purchaseOrder->update([
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
            ]);

            DB::commit();

            return $purchaseOrder->load(['supplier', 'items.product', 'createdBy']);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error creating purchase order', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            
            throw $e;
        }
    }

    /**
     * Update a purchase order
     */
    public function updatePurchaseOrder(int $purchaseOrderId, array $data)
    {
        $purchaseOrder = PurchaseOrder::findOrFail($purchaseOrderId);

        if ($purchaseOrder->status !== 'pending') {
            throw new \Exception('Cannot update purchase order that is not in pending status.');
        }

        DB::beginTransaction();
        try {
            $purchaseOrder->update([
                'supplier_id' => $data['supplier_id'] ?? $purchaseOrder->supplier_id,
                'warehouse_id' => $data['warehouse_id'] ?? $purchaseOrder->warehouse_id,
                'order_date' => $data['date'] ?? $purchaseOrder->order_date,
                'expected_date' => $data['expected_delivery_date'] ?? $purchaseOrder->expected_date,
                'notes' => $data['notes'] ?? $purchaseOrder->notes,
                'discount_amount' => $data['discount_amount'] ?? $purchaseOrder->discount_amount,
                'shipping_cost' => $data['shipping_cost'] ?? $purchaseOrder->shipping_cost,
            ]);

            if (isset($data['items'])) {
                // Delete existing items
                $purchaseOrder->items()->delete();

                $subtotal = 0;
                $taxAmount = 0;

                // Add new items
                foreach ($data['items'] as $item) {
                    $product = Product::findOrFail($item['product_id']);
                    $itemPrice = $item['unit_cost'];
                    $itemQty = $item['quantity'];
                    $itemTaxRate = $item['tax_rate'] ?? 0;
                    
                    $itemSubtotal = $itemPrice * $itemQty;
                    $itemTax = $itemSubtotal * ($itemTaxRate / 100);
                    $itemTotal = $itemSubtotal + $itemTax;

                    PurchaseOrderItem::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'product_id' => $product->id,
                        'unit_id' => $product->base_unit_id,
                        'quantity' => $itemQty,
                        'unit_price' => $itemPrice,
                        'tax' => $itemTax,
                        'total' => $itemTotal,
                        'tenant_id' => auth()->user()->tenant_id,
                    ]);

                    $subtotal += $itemSubtotal;
                    $taxAmount += $itemTax;
                }

                $total = $subtotal + $taxAmount + $purchaseOrder->shipping_cost - $purchaseOrder->discount_amount;
                $purchaseOrder->update([
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                ]);
            }

            DB::commit();

            return $purchaseOrder->load(['supplier', 'items.product', 'createdBy']);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error updating purchase order', [
                'error' => $e->getMessage(),
                'purchase_order_id' => $purchaseOrderId,
                'data' => $data
            ]);
            
            throw $e;
        }
    }

    /**
     * Delete a purchase order
     */
    public function deletePurchaseOrder(int $purchaseOrderId)
    {
        $purchaseOrder = PurchaseOrder::findOrFail($purchaseOrderId);

        if ($purchaseOrder->status !== 'pending') {
            throw new \Exception('Cannot delete purchase order that is not in pending status.');
        }

        $purchaseOrder->items()->delete();
        $purchaseOrder->delete();

        return true;
    }

    /**
     * Submit a purchase order
     */
    public function submitPurchaseOrder(int $purchaseOrderId)
    {
        $purchaseOrder = PurchaseOrder::findOrFail($purchaseOrderId);

        if ($purchaseOrder->status !== 'pending') {
            throw new \Exception('Cannot submit purchase order that is not in pending status.');
        }

        $purchaseOrder->update(['status' => 'submitted']);

        return $purchaseOrder;
    }

    /**
     * Receive a purchase order
     */
    public function receivePurchaseOrder(int $purchaseOrderId, array $data)
    {
        $purchaseOrder = PurchaseOrder::findOrFail($purchaseOrderId);

        if ($purchaseOrder->status !== 'submitted') {
            throw new \Exception('Purchase order must be submitted before receiving.');
        }

        DB::beginTransaction();
        try {
            // Update received quantities
            foreach ($data['received_items'] as $receivedItem) {
                $orderItem = $purchaseOrder->items()->where('product_id', $receivedItem['product_id'])->first();
                if (!$orderItem) {
                    throw new \Exception('Order item not found.');
                }

                if ($receivedItem['received_quantity'] > $orderItem->quantity) {
                    throw new \Exception('Received quantity cannot exceed ordered quantity.');
                }

                // Update stock levels
                $product = $orderItem->product;
                
                // Find or create stock level for this warehouse
                $stockLevel = StockLevel::firstOrCreate([
                    'tenant_id' => auth()->user()->tenant_id,
                    'warehouse_id' => $purchaseOrder->warehouse_id,
                    'product_id' => $product->id,
                ]);

                $quantityBefore = $stockLevel->quantity ?? 0;
                $stockLevel->increment('quantity', $receivedItem['received_quantity']);
                $quantityAfter = $stockLevel->fresh()->quantity;

                // Create stock movement record
                StockMovement::create([
                    'tenant_id' => auth()->user()->tenant_id,
                    'warehouse_id' => $purchaseOrder->warehouse_id,
                    'product_id' => $product->id,
                    'reference_type' => 'PurchaseOrder',
                    'reference_id' => $purchaseOrder->id,
                    'type' => 'in',
                    'quantity' => $receivedItem['received_quantity'],
                    'quantity_before' => $quantityBefore,
                    'quantity_after' => $quantityAfter,
                    'unit_cost' => $orderItem->unit_cost,
                    'created_by' => auth()->id(),
                ]);
            }

            $purchaseOrder->update(['status' => 'received', 'received_date' => now()]);

            DB::commit();

            return $purchaseOrder;
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error receiving purchase order', [
                'error' => $e->getMessage(),
                'purchase_order_id' => $purchaseOrderId,
                'data' => $data
            ]);
            
            throw $e;
        }
    }

    /**
     * Cancel a purchase order
     */
    public function cancelPurchaseOrder(int $purchaseOrderId)
    {
        $purchaseOrder = PurchaseOrder::findOrFail($purchaseOrderId);

        if ($purchaseOrder->status === 'received') {
            throw new \Exception('Cannot cancel purchase order that has already been received.');
        }

        $purchaseOrder->update(['status' => 'cancelled']);

        return $purchaseOrder;
    }

    /**
     * Get purchase orders with filters
     */
    public function getPurchaseOrdersWithFilters(array $filters = [], int $perPage = 15)
    {
        $query = PurchaseOrder::with(['supplier', 'items.product', 'createdBy']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('po_number', 'like', '%' . $filters['search'] . '%')
                  ->orWhereHas('supplier', function ($sub) use ($filters) {
                      $sub->where('name', 'like', '%' . $filters['search'] . '%');
                  });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        return $query->orderBy('created_at', 'desc')
                     ->paginate($perPage);
    }
}