<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OpeningStockEntry;
use App\Models\OpeningStockItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpeningStockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $entries = OpeningStockEntry::where('tenant_id', auth()->user()->tenant_id)
                                    ->with(['warehouse', 'creator'])
                                    ->orderByDesc('entry_date')
                                    ->paginate($request->per_page ?? 15);
        return response()->json(['success' => true, 'data' => $entries]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'entry_date'   => 'required|date',
            'reference'    => 'nullable|string|max:100',
            'items'        => 'required|array|min:1',
            'items.*.product_id'  => 'required|exists:products,id',
            'items.*.unit_id'     => 'required|exists:units,id',
            'items.*.quantity'    => 'required|numeric|min:0.0001',
            'items.*.unit_cost'   => 'required|numeric|min:0',
            'items.*.variant_id'  => 'nullable|exists:product_variants,id',
            'items.*.batch_id'    => 'nullable|exists:batches,id',
        ]);

        $entry = DB::transaction(function () use ($data) {
            $entry = OpeningStockEntry::create([
                'tenant_id'    => auth()->user()->tenant_id,
                'warehouse_id' => $data['warehouse_id'],
                'entry_date'   => $data['entry_date'],
                'reference'    => $data['reference'] ?? null,
                'status'       => 'draft',
                'created_by'   => auth()->id(),
            ]);

            foreach ($data['items'] as $item) {
                OpeningStockItem::create(array_merge($item, ['opening_stock_entry_id' => $entry->id]));
            }

            return $entry->load('items.product', 'warehouse');
        });

        return response()->json(['success' => true, 'data' => $entry, 'message' => 'Opening stock entry created in draft.'], 201);
    }

    public function show(OpeningStockEntry $openingStock): JsonResponse
    {
        $this->authorizeTenant($openingStock);
        return response()->json(['success' => true, 'data' => $openingStock->load('items.product', 'items.unit', 'warehouse', 'approver')]);
    }

    public function destroy(OpeningStockEntry $openingStock): JsonResponse
    {
        $this->authorizeTenant($openingStock);
        if (!$openingStock->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Only draft entries can be deleted.'], 422);
        }
        $openingStock->delete();
        return response()->json(['success' => true, 'message' => 'Entry deleted.']);
    }

    /**
     * Approve: seeds stock_levels and stock_movements for all items.
     */
    public function approve(OpeningStockEntry $openingStock): JsonResponse
    {
        $this->authorizeTenant($openingStock);

        if (!$openingStock->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Entry is already approved.'], 422);
        }

        DB::transaction(function () use ($openingStock) {
            foreach ($openingStock->items as $item) {
                // Upsert stock_levels
                $level = StockLevel::firstOrNew([
                    'tenant_id'    => $openingStock->tenant_id,
                    'product_id'   => $item->product_id,
                    'variant_id'   => $item->variant_id,
                    'warehouse_id' => $openingStock->warehouse_id,
                    'batch_id'     => $item->batch_id,
                ]);
                $level->quantity = ($level->quantity ?? 0) + $item->quantity;
                $level->save();

                // Stock movement
                StockMovement::create([
                    'tenant_id'    => $openingStock->tenant_id,
                    'product_id'   => $item->product_id,
                    'variant_id'   => $item->variant_id,
                    'warehouse_id' => $openingStock->warehouse_id,
                    'batch_id'     => $item->batch_id,
                    'unit_id'      => $item->unit_id,
                    'type'         => 'opening',
                    'quantity'     => $item->quantity,
                    'unit_cost'    => $item->unit_cost,
                    'reference'    => 'OPENING-' . $openingStock->id,
                    'created_by'   => auth()->id(),
                ]);
            }

            $openingStock->update([
                'status'      => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        });

        return response()->json(['success' => true, 'data' => $openingStock->fresh()->load('items.product'), 'message' => 'Opening stock approved and posted.']);
    }

    private function authorizeTenant(OpeningStockEntry $entry): void
    {
        abort_if($entry->tenant_id !== auth()->user()->tenant_id, 403);
    }
}
