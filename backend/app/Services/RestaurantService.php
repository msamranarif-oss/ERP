<?php

namespace App\Services;

use App\Models\RestaurantTable;
use App\Models\HeldSale;
use App\Models\HeldSaleItem;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RestaurantService
{
    /**
     * Get all available tables for a branch
     */
    public function getAvailableTables(int $branchId): array
    {
        return RestaurantTable::where('branch_id', $branchId)
            ->where('status', 'available')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    /**
     * Get tables by area
     */
    public function getTablesByArea(int $branchId, string $areaName): array
    {
        return RestaurantTable::where('branch_id', $branchId)
            ->where('area_name', $areaName)
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    /**
     * Open a table for dining
     */
    public function openTable(int $tableId, int $registerSessionId, ?int $customerId = null): HeldSale
    {
        $table = RestaurantTable::findOrFail($tableId);
        
        if ($table->status !== 'available') {
            throw new \Exception('Table is not available for dining');
        }

        return DB::transaction(function () use ($table, $registerSessionId, $customerId) {
            // Update table status
            $table->update(['status' => 'occupied']);
            
            // Create held sale for the table
            $heldSale = HeldSale::create([
                'tenant_id' => $table->tenant_id,
                'branch_id' => $table->branch_id,
                'register_session_id' => $registerSessionId,
                'customer_id' => $customerId,
                'reference' => 'TABLE-' . $table->name . '-' . now()->format('Hi'),
                'restaurant_table_id' => $table->getKey(),
                'order_type' => 'dine-in',
                'held_by' => auth()->id(),
                'status' => 'held',
                'subtotal' => 0,
                'total' => 0,
            ]);

            return $heldSale;
        });
    }

    /**
     * Close a table and generate bill
     */
    public function closeTable(int $tableId): Sale
    {
        $table = RestaurantTable::findOrFail($tableId);
        $heldSale = HeldSale::where('restaurant_table_id', $tableId)
            ->where('status', 'held')
            ->first();

        if (!$heldSale) {
            throw new \Exception('No active order found for this table');
        }

        return DB::transaction(function () use ($table, $heldSale) {
            // Update table status
            $table->update(['status' => 'billed']);
            
            // Convert held sale to actual sale
            $sale = Sale::create([
                'tenant_id' => $heldSale->tenant_id,
                'branch_id' => $heldSale->branch_id,
                'warehouse_id' => $this->getWarehouseId($heldSale),
                'customer_id' => $heldSale->customer_id,
                'register_session_id' => $heldSale->register_session_id,
                'sale_number' => 'SL-' . strtoupper(uniqid()),
                'sale_date' => now(),
                'subtotal' => $heldSale->subtotal,
                'discount_amount' => $heldSale->discount_amount,
                'tax_amount' => $heldSale->tax_amount,
                'total' => $heldSale->total,
                'payment_status' => 'unpaid',
                'status' => 'completed',
                'type' => 'dine-in',
                'restaurant_table_id' => $table->getKey(),
                'order_type' => 'dine-in',
                'sold_by' => auth()->id(),
            ]);

            // Copy items from held sale to sale
            foreach ($heldSale->items as $item) {
                $sale->items()->create([
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'unit_id' => $item->product->base_unit_id ?? 1,
                    'product_name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount_amount,
                    'tax' => $item->tax_amount,
                    'tax_rate' => $item->tax_percent,
                    'total' => $item->subtotal,
                ]);
            }

            // Mark held sale as retrieved
            $heldSale->update([
                'status' => 'retrieved',
                'sale_id' => $sale->getKey(),
            ]);

            return $sale;
        });
    }

    /**
     * Generate Kitchen Order Ticket
     */
    public function generateKOT(int $heldSaleId): array
    {
        $heldSale = HeldSale::with('items.product', 'restaurantTable')->findOrFail($heldSaleId);
        $items = $heldSale->items()->where('is_kot_printed', false)->get();

        if ($items->isEmpty()) {
            throw new \Exception('No new items to print for KOT');
        }

        DB::beginTransaction();
        try {
            // Mark items as printed
            $heldSale->items()->whereIn('id', $items->pluck('id'))->update(['is_kot_printed' => true]);

            $kotData = [
                'kot_number' => 'KOT-' . $heldSale->id . '-' . now()->format('is'),
                'table' => $heldSale->restaurantTable ? $heldSale->restaurantTable->name : 'N/A',
                'items' => $items->map(fn($item) => [
                    'name' => $item->product_name,
                    'qty' => $item->quantity,
                    'note' => $item->notes
                ])->toArray(),
                'printed_at' => now()->toDateTimeString(),
            ];

            DB::commit();
            return $kotData;
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Get pending kitchen orders
     */
    public function getPendingOrders(int $branchId): array
    {
        return HeldSale::with(['items.product', 'restaurantTable'])
            ->where('branch_id', $branchId)
            ->where('status', 'held')
            ->whereHas('items', function ($query) {
                $query->where('is_kot_printed', false);
            })
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Update table status
     */
    public function updateTableStatus(int $tableId, string $status): RestaurantTable
    {
        $table = RestaurantTable::findOrFail($tableId);
        $table->update(['status' => $status]);
        return $table;
    }

    /**
     * Calculate table turnover time
     */
    public function getTableTurnoverStats(int $branchId, int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        $tableStats = RestaurantTable::where('branch_id', $branchId)
            ->withCount(['heldSales' => function ($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate)
                    ->where('status', 'retrieved');
            }])
            ->get();

        return [
            'total_tables' => $tableStats->count(),
            'average_turnover' => $tableStats->avg('held_sales_count'),
            'busiest_tables' => $tableStats->sortByDesc('held_sales_count')->take(5),
            'period_days' => $days,
        ];
    }

    /**
     * Get warehouse ID for stock operations
     */
    private function getWarehouseId(HeldSale $heldSale): int
    {
        $user = auth()->user();
        if (!$user) {
            throw new \Exception('Authentication required');
        }
        
        if ($heldSale->register_session_id) {
            $registerSession = \App\Models\RegisterSession::with('cashRegister.branch.warehouses')
                ->find($heldSale->register_session_id);
            if ($registerSession && $registerSession->cashRegister && $registerSession->cashRegister->branch) {
                return $registerSession->cashRegister->branch->warehouses->first()->getKey() ?? 1;
            }
        }
        
        // Fallback to tenant default warehouse
        $warehouse = \App\Models\Warehouse::where('tenant_id', $user->tenant_id)->first();
        return $warehouse ? $warehouse->getKey() : 1;
    }
}