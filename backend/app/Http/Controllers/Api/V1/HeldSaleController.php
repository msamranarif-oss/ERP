<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HeldSale;
use App\Models\HeldSaleItem;
use App\Models\Sale;
use App\Services\POSService;
use App\Services\SequenceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HeldSaleController extends Controller
{
    protected SequenceService $sequenceService;
    protected POSService $posService;

    public function __construct(SequenceService $sequenceService, POSService $posService)
    {
        $this->sequenceService = $sequenceService;
        $this->posService      = $posService;
        // Fix 11: Add authorization — only users with the correct policy can manage held sales
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', HeldSale::class);

        $query = HeldSale::with(['customer', 'items.product'])
            ->where('tenant_id', auth()->user()->tenant_id);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('hold_number', 'like', '%' . $request->search . '%')
                  ->orWhereHas('customer', function ($sub) use ($request) {
                      $sub->where('name', 'like', '%' . $request->search . '%');
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $holds = $query->orderBy('created_at', 'desc')
                       ->paginate($request->per_page ?? 15);

        return response()->json(['success' => true, 'data' => $holds]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', HeldSale::class);

        $validated = $request->validate([
            'register_session_id'       => 'nullable|exists:register_sessions,id',
            'customer_id'               => 'nullable|exists:customers,id',
            'items'                     => 'required|array|min:1',
            'items.*.product_id'        => 'required|exists:products,id',
            'items.*.quantity'          => 'required|numeric|min:0.0001',
            'items.*.unit_price'        => 'required|numeric|min:0',
            'items.*.discount_percent'  => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount'   => 'nullable|numeric|min:0',
            'items.*.tax_percent'       => 'nullable|numeric|min:0|max:100',
            'items.*.tax_amount'        => 'nullable|numeric|min:0',
            'discount_amount'           => 'nullable|numeric|min:0',
            'tax_amount'                => 'nullable|numeric|min:0',
            'shipping_cost'             => 'nullable|numeric|min:0',
            'total_amount'              => 'required|numeric|min:0',
            'notes'                     => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            // Fix 14: Use SequenceService instead of raw count
            $holdNumber = $this->sequenceService->generateReference('held_sale', 'HD');

            $heldSale = HeldSale::create([
                'reference'         => $holdNumber,
                'tenant_id'         => auth()->user()->tenant_id,
                'branch_id'         => auth()->user()->branch_id,
                'register_session_id' => $validated['register_session_id'] ?? null,
                'customer_id'       => $validated['customer_id'] ?? null,
                'subtotal'          => $validated['total_amount'] - ($validated['tax_amount'] ?? 0) - ($validated['shipping_cost'] ?? 0),
                'discount_amount'   => $validated['discount_amount'] ?? 0,
                'tax_amount'        => $validated['tax_amount'] ?? 0,
                'shipping_cost'     => $validated['shipping_cost'] ?? 0,
                'total'             => $validated['total_amount'],
                'items'             => json_encode($validated['items']),
                'notes'             => $validated['notes'] ?? null,
                'status'            => 'held',
                'held_by'           => auth()->id(),
            ]);

            $warehouseId = $this->getWarehouseId($heldSale);

            // Fix 1: Write to held_sale_items table (not a missing table)
            foreach ($validated['items'] as $item) {
                $lineSubtotal = (float)$item['quantity'] * (float)$item['unit_price'];
                HeldSaleItem::create([
                    'held_sale_id'     => $heldSale->id,
                    'product_id'       => $item['product_id'],
                    'tenant_id'        => auth()->user()->tenant_id,
                    'quantity'         => $item['quantity'],
                    'unit_price'       => $item['unit_price'],
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'discount_amount'  => $item['discount_amount'] ?? 0,
                    'tax_percent'      => $item['tax_percent'] ?? 0,
                    'tax_amount'       => $item['tax_amount'] ?? 0,
                    'subtotal'         => $lineSubtotal,
                ]);

                // Reserve Stock (with batch + conversion factor support)
                if ($warehouseId) {
                    $conversionFactor = (float) ($item['conversion_factor'] ?? 1);
                    $baseQtyToReserve = (float) $item['quantity'] * $conversionFactor;
                    $stockLevel = \App\Models\StockLevel::firstOrCreate([
                        'tenant_id'    => auth()->user()->tenant_id,
                        'warehouse_id' => $warehouseId,
                        'product_id'   => $item['product_id'],
                        'variant_id'   => $item['variant_id'] ?? null,
                        'batch_id'     => $item['batch_id'] ?? null,
                    ]);
                    $stockLevel->increment('reserved_quantity', $baseQtyToReserve);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data'    => $heldSale->load(['customer', 'items.product']),
                'message' => 'Sale held successfully.',
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => 'Failed to hold sale: ' . $e->getMessage()], 500);
        }
    }

    public function show(HeldSale $held_sale)
    {
        $this->authorize('view', $held_sale);

        return response()->json([
            'success' => true,
            'data'    => $held_sale->load(['customer', 'items.product']),
        ]);
    }

    public function update(Request $request, HeldSale $held_sale)
    {
        $this->authorize('update', $held_sale);

        $validated = $request->validate([
            'customer_id'               => 'sometimes|nullable|exists:customers,id',
            'items'                     => 'sometimes|required|array|min:1',
            'items.*.product_id'        => 'sometimes|required|exists:products,id',
            'items.*.quantity'          => 'sometimes|required|numeric|min:0.0001',
            'items.*.unit_price'        => 'sometimes|required|numeric|min:0',
            'items.*.discount_percent'  => 'sometimes|nullable|numeric|min:0|max:100',
            'items.*.discount_amount'   => 'sometimes|nullable|numeric|min:0',
            'items.*.tax_percent'       => 'sometimes|nullable|numeric|min:0|max:100',
            'items.*.tax_amount'        => 'sometimes|nullable|numeric|min:0',
            'discount_amount'           => 'sometimes|nullable|numeric|min:0',
            'tax_amount'                => 'sometimes|nullable|numeric|min:0',
            'shipping_cost'             => 'sometimes|nullable|numeric|min:0',
            'total_amount'              => 'sometimes|required|numeric|min:0',
            'notes'                     => 'sometimes|nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $warehouseId = $this->getWarehouseId($held_sale);

            // Release old reserved stock before applying updates (with conversion factor)
            if ($warehouseId) {
                foreach ($held_sale->items as $oldItem) {
                    $oldConversion = (float) ($oldItem->conversion_factor ?? 1);
                    $oldBaseQty = (float) $oldItem->quantity * $oldConversion;
                    $stockLevel = \App\Models\StockLevel::where([
                        'tenant_id'    => auth()->user()->tenant_id,
                        'warehouse_id' => $warehouseId,
                        'product_id'   => $oldItem->product_id,
                        'variant_id'   => $oldItem->variant_id,
                        'batch_id'     => $oldItem->batch_id ?? null,
                    ])->first();
                    if ($stockLevel) {
                        $stockLevel->decrement('reserved_quantity', $oldBaseQty);
                    }
                }
            }

            $held_sale->update([
                'customer_id'   => $validated['customer_id'] ?? $held_sale->customer_id,
                'subtotal'      => isset($validated['total_amount'])
                    ? $validated['total_amount'] - ($validated['tax_amount'] ?? 0) - ($validated['shipping_cost'] ?? 0)
                    : $held_sale->subtotal,
                'discount_amount' => $validated['discount_amount'] ?? $held_sale->discount_amount,
                'tax_amount'    => $validated['tax_amount'] ?? $held_sale->tax_amount,
                'shipping_cost' => $validated['shipping_cost'] ?? $held_sale->shipping_cost,
                'total'         => $validated['total_amount'] ?? $held_sale->total,
                'notes'         => $validated['notes'] ?? $held_sale->notes,
            ]);

            if (isset($validated['items'])) {
                $held_sale->items()->delete();
                foreach ($validated['items'] as $item) {
                    $lineSubtotal = (float)$item['quantity'] * (float)$item['unit_price'];
                    HeldSaleItem::create([
                        'held_sale_id'     => $held_sale->id,
                        'product_id'       => $item['product_id'],
                        'tenant_id'        => auth()->user()->tenant_id,
                        'quantity'         => $item['quantity'],
                        'unit_price'       => $item['unit_price'],
                        'discount_percent' => $item['discount_percent'] ?? 0,
                        'discount_amount'  => $item['discount_amount'] ?? 0,
                        'tax_percent'      => $item['tax_percent'] ?? 0,
                        'tax_amount'       => $item['tax_amount'] ?? 0,
                        'subtotal'         => $lineSubtotal,
                    ]);

                    // Reserve New Stock (with batch + conversion factor support)
                    if ($warehouseId) {
                        $conversionFactor = (float) ($item['conversion_factor'] ?? 1);
                        $baseQtyToReserve = (float) $item['quantity'] * $conversionFactor;
                        $stockLevel = \App\Models\StockLevel::firstOrCreate([
                            'tenant_id'    => auth()->user()->tenant_id,
                            'warehouse_id' => $warehouseId,
                            'product_id'   => $item['product_id'],
                            'variant_id'   => $item['variant_id'] ?? null,
                            'batch_id'     => $item['batch_id'] ?? null,
                        ]);
                        $stockLevel->increment('reserved_quantity', $baseQtyToReserve);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data'    => $held_sale->load(['customer', 'items.product']),
                'message' => 'Held sale updated successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => 'Failed to update held sale: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(HeldSale $held_sale)
    {
        $this->authorize('delete', $held_sale);

        $warehouseId = $this->getWarehouseId($held_sale);
        if ($warehouseId) {
            foreach ($held_sale->items as $item) {
                $conversionFactor = (float) ($item->conversion_factor ?? 1);
                $baseQtyToRelease = (float) $item->quantity * $conversionFactor;
                $stockLevel = \App\Models\StockLevel::where([
                    'tenant_id'    => auth()->user()->tenant_id,
                    'warehouse_id' => $warehouseId,
                    'product_id'   => $item->product_id,
                    'variant_id'   => $item->variant_id,
                    'batch_id'     => $item->batch_id ?? null,
                ])->first();
                if ($stockLevel) {
                    $stockLevel->decrement('reserved_quantity', $baseQtyToRelease);
                }
            }
        }

        $held_sale->items()->delete();
        $held_sale->delete();

        return response()->json(['success' => true, 'message' => 'Held sale deleted successfully.']);
    }

    /**
     * Fix 4: Retrieve a held sale — convert it to a real POS sale.
     * Marks the held sale as 'retrieved' and creates a Sale record via POSService.
     */
    public function retrieve(HeldSale $held_sale, Request $request)
    {
        $this->authorize('update', $held_sale);

        if ($held_sale->status !== 'held') {
            return response()->json([
                'success' => false,
                'message' => 'This held sale has already been retrieved or cancelled.',
            ], 422);
        }

        $validated = $request->validate([
            'register_session_id' => 'required|exists:register_sessions,id',
            'payments'            => 'required|array|min:1',
            'payments.*.payment_method_id' => 'required|exists:payment_methods,id',
            'payments.*.amount'   => 'required|numeric|min:0',
            'payments.*.reference' => 'nullable|string|max:255',
            'paid_amount'         => 'required|numeric|min:0',
            'change_amount'       => 'required|numeric|min:0',
            'notes'               => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            // Build the data array expected by POSService::createSale()
            $saleData = [
                'register_session_id' => $validated['register_session_id'],
                'customer_id'         => $held_sale->customer_id,
                'items'               => $held_sale->items->map(function ($item) {
                    return [
                        'product_id'       => $item->product_id,
                        'variant_id'       => $item->variant_id,
                        'quantity'         => $item->quantity,
                        'unit_price'       => $item->unit_price,
                        'discount_amount'  => $item->discount_amount,
                        'discount_percent' => $item->discount_percent,
                        'tax_amount'       => $item->tax_amount,
                        'tax_percent'      => $item->tax_percent,
                    ];
                })->toArray(),
                'discount_amount'     => $held_sale->discount_amount,
                'tax_amount'          => $held_sale->tax_amount,
                'shipping_cost'       => $held_sale->shipping_cost ?? 0,
                'total_amount'        => $held_sale->total,
                'paid_amount'         => $validated['paid_amount'],
                'change_amount'       => $validated['change_amount'],
                'payments'            => $validated['payments'],
                'notes'               => $validated['notes'] ?? $held_sale->notes,
                'type'                => 'walk-in',
                'restaurant_table_id' => $held_sale->restaurant_table_id,
                'order_type'          => $held_sale->order_type,
            ];

            $sale = $this->posService->createSale($saleData);

            // Mark the held sale as retrieved
            $held_sale->update([
                'status'       => 'retrieved',
                'sale_id'      => $sale->id,
            ]);

            // Update Restaurant Table Status if applicable
            if ($held_sale->restaurantTable) {
                $held_sale->restaurantTable->update(['status' => 'available']);
            }

            // Release the reserved stock
            $warehouseId = $this->getWarehouseId($held_sale);
            if ($warehouseId) {
                foreach ($held_sale->items as $item) {
                    $conversionFactor = (float) ($item->conversion_factor ?? 1);
                    $baseQtyToRelease = (float) $item->quantity * $conversionFactor;
                    $stockLevel = \App\Models\StockLevel::where([
                        'tenant_id'    => auth()->user()->tenant_id,
                        'warehouse_id' => $warehouseId,
                        'product_id'   => $item->product_id,
                        'variant_id'   => $item->variant_id,
                        'batch_id'     => $item->batch_id ?? null,
                    ])->first();
                    if ($stockLevel) {
                        $stockLevel->decrement('reserved_quantity', $baseQtyToRelease);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data'    => new \App\Http\Resources\SaleResource($sale),
                'message' => 'Held sale retrieved and converted to a sale successfully.',
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private function getWarehouseId(HeldSale $heldSale)
    {
        if ($heldSale->register_session_id) {
            $registerSession = \App\Models\RegisterSession::with('cashRegister.branch.warehouses')->find($heldSale->register_session_id);
            if ($registerSession && $registerSession->cashRegister && $registerSession->cashRegister->branch) {
                return $registerSession->cashRegister->branch->warehouses->first()->id ?? null;
            }
        }
        
        // Fallback to tenant default warehouse
        $warehouse = \App\Models\Warehouse::where('tenant_id', auth()->user()->tenant_id)->first();
        return $warehouse->id ?? null;
    }
}