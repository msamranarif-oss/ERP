<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\Warehouse;
use App\Models\Product;
use App\Services\JournalAutoPostService;
use App\Services\StockAdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockAdjustmentController extends Controller
{
    protected $stockAdjustmentService;
    protected JournalAutoPostService $autoPostService;

    public function __construct(
        StockAdjustmentService $stockAdjustmentService,
        JournalAutoPostService $autoPostService
    ) {
        $this->stockAdjustmentService = $stockAdjustmentService;
        $this->autoPostService        = $autoPostService;
        $this->authorizeResource(StockAdjustment::class, 'stock_adjustment');
    }
   

    public function index(Request $request)
    {
        $query = StockAdjustment::with(['warehouse', 'items.product', 'createdBy', 'approvedBy']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('adjustment_number', 'like', '%' . $request->search . '%')
                  ->orWhereHas('warehouse', function ($sub) use ($request) {
                      $sub->where('name', 'like', '%' . $request->search . '%');
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('adjustment_type')) {
            $query->where('adjustment_type', $request->adjustment_type);
        }

        $adjustments = $query->orderBy('created_at', 'desc')
                            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $adjustments
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'adjustment_type' => 'required|in:addition,subtraction',
            'date' => 'required|date',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.reason' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $stockAdjustment = StockAdjustment::create([
                'adjustment_number' => 'SA-' . date('Y') . '-' . str_pad(StockAdjustment::whereYear('created_at', date('Y'))->count() + 1, 4, '0', STR_PAD_LEFT),
                'warehouse_id' => $validated['warehouse_id'],
                'adjustment_type' => $validated['adjustment_type'],
                'date' => $validated['date'],
                'reason' => $validated['reason'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'pending',
                'tenant_id' => request()->user()->tenant_id,
                'created_by' => request()->user()->id,
            ]);

            foreach ($validated['items'] as $item) {
                $stockAdjustment->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'] ?? 0,
                    'reason' => $item['reason'] ?? $validated['reason'],
                    'tenant_id' => request()->user()->tenant_id,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $stockAdjustment->load(['warehouse', 'items.product', 'createdBy', 'approvedBy']),
                'message' => 'Stock adjustment created successfully.'
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'An internal error occurred. Please try again later.'
            ], 500);
        }
    }

    public function show(StockAdjustment $stock_adjustment)
    {
        return response()->json([
            'success' => true,
            'data' => $stock_adjustment->load(['warehouse', 'items.product', 'createdBy', 'approvedBy'])
        ]);
    }

    public function update(Request $request, StockAdjustment $stock_adjustment)
    {
        if ($stock_adjustment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update stock adjustment that is not in pending status.'
            ], 422);
        }

        $validated = $request->validate([
            'warehouse_id' => 'sometimes|required|exists:warehouses,id',
            'adjustment_type' => 'sometimes|required|in:addition,subtraction',
            'date' => 'sometimes|required|date',
            'reason' => 'sometimes|required|string|max:255',
            'notes' => 'sometimes|nullable|string|max:1000',
            'items' => 'sometimes|required|array|min:1',
            'items.*.product_id' => 'sometimes|required|exists:products,id',
            'items.*.quantity' => 'sometimes|required|integer|min:1',
            'items.*.unit_cost' => 'sometimes|nullable|numeric|min:0',
            'items.*.reason' => 'sometimes|nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $stockAdjustment = $stock_adjustment;
            
            $stockAdjustment->update([
                'warehouse_id' => $validated['warehouse_id'] ?? $stockAdjustment->warehouse_id,
                'adjustment_type' => $validated['adjustment_type'] ?? $stockAdjustment->adjustment_type,
                'date' => $validated['date'] ?? $stockAdjustment->date,
                'reason' => $validated['reason'] ?? $stockAdjustment->reason,
                'notes' => $validated['notes'] ?? $stockAdjustment->notes,
            ]);

            if (isset($validated['items'])) {
                // Delete existing items
                $stockAdjustment->items()->delete();

                // Add new items
                foreach ($validated['items'] as $item) {
                    $stockAdjustment->items()->create([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_cost' => $item['unit_cost'] ?? 0,
                        'reason' => $item['reason'] ?? $validated['reason'],
                        'tenant_id' => request()->user()->tenant_id,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $stockAdjustment->load(['warehouse', 'items.product', 'createdBy', 'approvedBy']),
                'message' => 'Stock adjustment updated successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update stock adjustment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(StockAdjustment $stock_adjustment)
    {
        if ($stock_adjustment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete stock adjustment that is not in pending status.'
            ], 422);
        }

        $stock_adjustment->items()->delete();
        $stock_adjustment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Stock adjustment deleted successfully.'
        ]);
    }

    public function approve(StockAdjustment $stock_adjustment, Request $request)
    {
        if ($stock_adjustment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot approve stock adjustment that is not in pending status.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Fix 13: Apply the actual quantity changes to stock levels
            foreach ($stock_adjustment->items as $item) {
                $stockLevel = \App\Models\StockLevel::firstOrCreate([
                    'tenant_id'    => $stock_adjustment->tenant_id,
                    'warehouse_id' => $stock_adjustment->warehouse_id,
                    'product_id'   => $item->product_id,
                    'variant_id'   => null,
                    'batch_id'     => null,
                ]);

                $quantityBefore = $stockLevel->quantity ?? 0;

                if ($stock_adjustment->adjustment_type === 'addition') {
                    $stockLevel->increment('quantity', $item->quantity);
                } else {
                    // subtraction — guard against going negative
                    $deduct = min($stockLevel->quantity, $item->quantity);
                    $stockLevel->decrement('quantity', $deduct);
                }

                $quantityAfter = $stockLevel->fresh()->quantity;

                \App\Models\StockMovement::create([
                    'tenant_id'       => $stock_adjustment->tenant_id,
                    'warehouse_id'    => $stock_adjustment->warehouse_id,
                    'product_id'      => $item->product_id,
                    'reference_type'  => 'StockAdjustment',
                    'reference_id'    => $stock_adjustment->id,
                    'type'            => $stock_adjustment->adjustment_type === 'addition' ? 'in' : 'out',
                    'quantity'        => $item->quantity,
                    'quantity_before' => $quantityBefore,
                    'quantity_after'  => $quantityAfter,
                    'unit_cost'       => $item->unit_cost ?? 0,
                    'notes'           => $item->reason,
                    'created_by'      => request()->user()->id,
                ]);
            }

            $stock_adjustment->update([
                'status'      => 'approved',
                'approved_by' => request()->user()->id,
                'approved_at' => now(),
            ]);

            DB::commit();

            // Task 7: Post inventory-valuation journal entry (non-fatal)
            try {
                $this->autoPostService->postStockAdjustment($stock_adjustment);
            } catch (\Exception $e) {
                Log::warning('postStockAdjustment failed', [
                    'adjustment_id' => $stock_adjustment->id,
                    'error'         => $e->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'data'    => $stock_adjustment->load(['warehouse', 'items.product', 'createdBy', 'approvedBy']),
                'message' => 'Stock adjustment approved and stock levels updated successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve stock adjustment: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function reject(StockAdjustment $stock_adjustment, Request $request)
    {
        if ($stock_adjustment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot reject stock adjustment that is not in pending status.'
            ], 422);
        }

        $stock_adjustment->update([
            'status' => 'rejected',
            'rejected_by' => request()->user()->id,
            'rejected_at' => now(),
            'rejection_reason' => $request->rejection_reason
        ]);

        return response()->json([
            'success' => true,
            'data' => $stock_adjustment,
            'message' => 'Stock adjustment rejected successfully.'
        ]);
    }
}