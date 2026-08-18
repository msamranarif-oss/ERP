<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseReturnController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $returns = PurchaseReturn::where('tenant_id', $tenantId)
            ->with(['supplier', 'items.product', 'creator'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->supplier_id, fn($q, $id) => $q->where('supplier_id', $id))
            ->orderByDesc('return_date')
            ->paginate($request->get('per_page', 15));

        return $this->successResponse($returns);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id'      => 'required|exists:suppliers,id',
            'grn_id'           => 'nullable|exists:goods_received_notes,id',
            'warehouse_id'     => 'nullable|exists:warehouses,id',
            'return_date'      => 'required|date',
            'reason'           => 'nullable|string',
            'items'            => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.unit_id'    => 'required|exists:units,id',
            'items.*.quantity'   => 'required|numeric|min:0.0001',
            'items.*.unit_cost'  => 'required|numeric|min:0',
            'items.*.reason'     => 'nullable|string',
        ]);

        $tenantId = $request->user()->tenant_id;

        return DB::transaction(function () use ($data, $tenantId, $request) {
            $totalAmount = collect($data['items'])->sum(fn($i) => $i['quantity'] * $i['unit_cost']);

            $return = PurchaseReturn::create([
                'tenant_id'     => $tenantId,
                'supplier_id'   => $data['supplier_id'],
                'grn_id'        => $data['grn_id'] ?? null,
                'warehouse_id'  => $data['warehouse_id'] ?? null,
                'return_number' => $this->generateReturnNumber($tenantId),
                'return_date'   => $data['return_date'],
                'status'        => 'pending',
                'reason'        => $data['reason'] ?? null,
                'total_amount'  => $totalAmount,
                'created_by'    => $request->user()->id,
            ]);

            foreach ($data['items'] as $item) {
                PurchaseReturnItem::create([
                    'purchase_return_id' => $return->id,
                    'product_id'         => $item['product_id'],
                    'variant_id'         => $item['variant_id'] ?? null,
                    'unit_id'            => $item['unit_id'],
                    'quantity'           => $item['quantity'],
                    'unit_cost'          => $item['unit_cost'],
                    'total'              => $item['quantity'] * $item['unit_cost'],
                    'reason'             => $item['reason'] ?? null,
                ]);
            }

            return $this->successResponse(
                $return->load('items.product', 'supplier'),
                'Purchase return created', 201
            );
        });
    }

    public function show(Request $request, PurchaseReturn $purchaseReturn): JsonResponse
    {
        abort_if($purchaseReturn->tenant_id !== $request->user()->tenant_id, 403);
        return $this->successResponse($purchaseReturn->load('items.product', 'items.variant', 'items.unit', 'supplier', 'grn'));
    }

    public function destroy(Request $request, PurchaseReturn $purchaseReturn): JsonResponse
    {
        abort_if($purchaseReturn->tenant_id !== $request->user()->tenant_id, 403);
        abort_if($purchaseReturn->status === 'approved', 422, 'Approved returns cannot be deleted');
        $purchaseReturn->delete();
        return $this->successResponse(null, 'Purchase return deleted');
    }

    public function approve(Request $request, PurchaseReturn $purchaseReturn): JsonResponse
    {
        abort_if($purchaseReturn->tenant_id !== $request->user()->tenant_id, 403);
        abort_if($purchaseReturn->status !== 'pending', 422, 'Only pending returns can be approved');

        return DB::transaction(function () use ($purchaseReturn, $request) {
            $warehouseId = $purchaseReturn->warehouse_id;

            foreach ($purchaseReturn->items as $item) {
                // Reverse the stock: add back what was returned
                $stockLevel = StockLevel::firstOrCreate(
                    [
                        'tenant_id'    => $purchaseReturn->tenant_id,
                        'product_id'   => $item->product_id,
                        'variant_id'   => $item->variant_id,
                        'warehouse_id' => $warehouseId,
                    ],
                    ['quantity' => 0, 'reserved_quantity' => 0, 'avg_cost' => 0]
                );

                $before = $stockLevel->quantity;

                // Subtract from stock (returning to supplier means stock goes down)
                $newQty = max(0, $before - $item->quantity);
                $stockLevel->update(['quantity' => $newQty]);

                StockMovement::create([
                    'tenant_id'      => $purchaseReturn->tenant_id,
                    'product_id'     => $item->product_id,
                    'variant_id'     => $item->variant_id,
                    'warehouse_id'   => $warehouseId,
                    'unit_id'        => $item->unit_id,
                    'type'           => 'return',
                    'reference_type' => PurchaseReturn::class,
                    'reference_id'   => $purchaseReturn->id,
                    'quantity'       => -$item->quantity,
                    'unit_cost'      => $item->unit_cost,
                    'quantity_before'=> $before,
                    'quantity_after' => $newQty,
                    'notes'          => 'Purchase return: ' . $purchaseReturn->return_number,
                    'created_by'     => $request->user()->id,
                ]);
            }

            $purchaseReturn->update([
                'status'      => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            return $this->successResponse($purchaseReturn->fresh(), 'Purchase return approved');
        });
    }

    private function generateReturnNumber(int $tenantId): string
    {
        $year    = (int) date('Y');
        $counter = DB::transaction(function () use ($tenantId, $year) {
            $row = DB::table('sequence_counters')
                ->where('tenant_id', $tenantId)
                ->where('type', 'purchase_return')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($row) {
                DB::table('sequence_counters')
                    ->where('id', $row->id)
                    ->increment('current_value');
                return $row->current_value + 1;
            }

            DB::table('sequence_counters')->insert([
                'tenant_id'     => $tenantId,
                'type'          => 'purchase_return',
                'year'          => $year,
                'current_value' => 1,
            ]);
            return 1;
        });

        return 'PR-' . $year . '-' . str_pad($counter, 4, '0', STR_PAD_LEFT);
    }
}
