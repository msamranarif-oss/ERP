<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Services\BatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function __construct(private BatchService $service) {}

    public function index(Request $request): JsonResponse
    {
        $query = Batch::with(['product', 'warehouse', 'supplier'])
                      ->where('tenant_id', auth()->user()->tenant_id);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }
        if ($request->filled('expiring_in')) {
            $query->expiringSoon((int) $request->expiring_in);
        }

        return response()->json(['success' => true, 'data' => $query->orderByDesc('created_at')->paginate($request->per_page ?? 15)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'          => 'required|exists:products,id',
            'warehouse_id'        => 'nullable|exists:warehouses,id',
            'batch_number'        => 'required|string|max:100',
            'manufacturing_date'  => 'nullable|date',
            'expiry_date'         => 'nullable|date',
            'quantity_received'   => 'required|numeric|min:0.0001',
            'cost_price'          => 'required|numeric|min:0',
            'supplier_id'         => 'nullable|exists:suppliers,id',
            'lot_id'              => 'nullable|exists:lots,id',
            'notes'               => 'nullable|string',
        ]);

        $data['tenant_id']            = auth()->user()->tenant_id;
        $data['quantity_remaining']   = $data['quantity_received'];
        $data['created_by']           = auth()->id();

        $batch = $this->service->createBatch($data);

        return response()->json(['success' => true, 'data' => $batch, 'message' => 'Batch created.'], 201);
    }

    public function show(Batch $batch): JsonResponse
    {
        $this->authorizeTenant($batch);
        return response()->json(['success' => true, 'data' => $batch->load(['product', 'warehouse', 'supplier', 'lot', 'serialNumbers'])]);
    }

    public function update(Request $request, Batch $batch): JsonResponse
    {
        $this->authorizeTenant($batch);
        $data = $request->validate([
            'status'  => 'sometimes|in:active,expired,quarantine,recalled,depleted',
            'notes'   => 'nullable|string',
            'bin_location' => 'nullable|string',
        ]);
        $batch->update($data);
        return response()->json(['success' => true, 'data' => $batch->fresh()]);
    }

    public function transfer(Request $request, Batch $batch): JsonResponse
    {
        $this->authorizeTenant($batch);
        $data = $request->validate(['to_warehouse_id' => 'required|exists:warehouses,id', 'quantity' => 'required|numeric|min:0.0001']);
        $new  = $this->service->transferBatch($batch->id, $data['to_warehouse_id'], $data['quantity']);
        return response()->json(['success' => true, 'data' => $new, 'message' => 'Batch transferred.']);
    }

    public function recall(Request $request, Batch $batch): JsonResponse
    {
        $this->authorizeTenant($batch);
        $request->validate(['reason' => 'required|string']);
        $batch = $this->service->recallBatch($batch->id, $request->reason);
        return response()->json(['success' => true, 'data' => $batch, 'message' => 'Batch recalled.']);
    }

    public function split(Request $request, Batch $batch): JsonResponse
    {
        $this->authorizeTenant($batch);
        $request->validate(['quantity' => 'required|numeric|min:0.0001']);
        $split = $this->service->splitBatch($batch->id, $request->quantity);
        return response()->json(['success' => true, 'data' => $split, 'message' => 'Batch split.'], 201);
    }

    public function merge(Request $request): JsonResponse
    {
        $request->validate(['batch_ids' => 'required|array|min:2', 'batch_ids.*' => 'exists:batches,id']);
        $merged = $this->service->mergeBatches($request->batch_ids);
        return response()->json(['success' => true, 'data' => $merged, 'message' => 'Batches merged.']);
    }

    public function expiryAlerts(Request $request): JsonResponse
    {
        $days  = (int) $request->get('days', 30);
        $items = $this->service->getExpiryAlerts($days);
        return response()->json(['success' => true, 'data' => $items]);
    }

    public function byProduct(int $productId): JsonResponse
    {
        $batches = Batch::where('tenant_id', auth()->user()->tenant_id)
                        ->where('product_id', $productId)
                        ->with(['warehouse'])
                        ->orderBy('expiry_date')
                        ->get();
        return response()->json(['success' => true, 'data' => $batches]);
    }

    private function authorizeTenant(Batch $batch): void
    {
        abort_if($batch->tenant_id !== auth()->user()->tenant_id, 403);
    }
}
