<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SerialNumber;
use App\Services\SerialNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SerialNumberController extends Controller
{
    public function __construct(private SerialNumberService $service) {}

    public function index(Request $request): JsonResponse
    {
        $query = SerialNumber::with(['product', 'warehouse'])
                             ->where('tenant_id', auth()->user()->tenant_id);

        if ($request->filled('product_id'))   $query->where('product_id', $request->product_id);
        if ($request->filled('status'))        $query->where('status', $request->status);
        if ($request->filled('warehouse_id'))  $query->where('warehouse_id', $request->warehouse_id);

        return response()->json(['success' => true, 'data' => $query->paginate($request->per_page ?? 25)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'   => 'required|exists:products,id',
            'variant_id'   => 'nullable|exists:product_variants,id',
            'batch_id'     => 'nullable|exists:batches,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'serial_number' => 'required|string|max:100',
            'imei'          => 'nullable|string|max:20',
            'warranty_expiry' => 'nullable|date',
            'notes'         => 'nullable|string',
        ]);
        $data['tenant_id']  = auth()->user()->tenant_id;
        $data['created_by'] = auth()->id();

        $serial = SerialNumber::create($data);
        return response()->json(['success' => true, 'data' => $serial], 201);
    }

    public function bulkStore(Request $request): JsonResponse
    {
        $request->validate(['serials' => 'required|array|min:1', 'serials.*.serial_number' => 'required|string', 'serials.*.product_id' => 'required|exists:products,id']);

        $items = collect($request->serials)->map(fn($s) => array_merge($s, [
            'tenant_id'  => auth()->user()->tenant_id,
            'created_by' => auth()->id(),
        ]))->toArray();

        $created = $this->service->bulkCreate($items);
        return response()->json(['success' => true, 'data' => $created, 'message' => "Created {$created->count()} serial numbers."], 201);
    }

    public function show(SerialNumber $serial): JsonResponse
    {
        abort_if($serial->tenant_id !== auth()->user()->tenant_id, 403);
        return response()->json(['success' => true, 'data' => $serial->load(['product', 'batch', 'warehouse', 'customer'])]);
    }

    public function update(Request $request, SerialNumber $serial): JsonResponse
    {
        abort_if($serial->tenant_id !== auth()->user()->tenant_id, 403);
        $data = $request->validate(['notes' => 'nullable|string', 'warranty_expiry' => 'nullable|date']);
        $serial->update($data);
        return response()->json(['success' => true, 'data' => $serial->fresh()]);
    }

    public function markDefective(Request $request, SerialNumber $serial): JsonResponse
    {
        abort_if($serial->tenant_id !== auth()->user()->tenant_id, 403);
        $request->validate(['reason' => 'required|string']);
        $serial = $this->service->markDefective($serial->id, $request->reason);
        return response()->json(['success' => true, 'data' => $serial, 'message' => 'Serial marked defective.']);
    }

    public function search(Request $request): JsonResponse
    {
        $q       = $request->validate(['q' => 'required|string|min:2'])['q'];
        $results = $this->service->search($q);
        return response()->json(['success' => true, 'data' => $results]);
    }

    public function byProduct(int $productId): JsonResponse
    {
        $serials = SerialNumber::where('tenant_id', auth()->user()->tenant_id)
                               ->where('product_id', $productId)
                               ->with('warehouse')
                               ->get();
        return response()->json(['success' => true, 'data' => $serials]);
    }
}
