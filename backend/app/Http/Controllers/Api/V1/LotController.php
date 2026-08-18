<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Lot;
use App\Services\LotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LotController extends Controller
{
    public function __construct(private LotService $service) {}

    public function index(Request $request): JsonResponse
    {
        $query = Lot::with(['supplier'])
                    ->where('tenant_id', auth()->user()->tenant_id);

        if ($request->filled('status'))    $query->where('status', $request->status);
        if ($request->filled('qc_status')) $query->where('qc_status', $request->qc_status);

        return response()->json(['success' => true, 'data' => $query->orderByDesc('created_at')->paginate($request->per_page ?? 15)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id'   => 'nullable|exists:suppliers,id',
            'lot_number'    => 'required|string|max:100',
            'received_date' => 'nullable|date',
            'quantity'      => 'required|numeric|min:0.0001',
        ]);
        $data['tenant_id'] = auth()->user()->tenant_id;

        $lot = $this->service->createLot($data);
        return response()->json(['success' => true, 'data' => $lot], 201);
    }

    public function show(Lot $lot): JsonResponse
    {
        $this->authorizeTenant($lot);
        return response()->json(['success' => true, 'data' => $lot->load(['supplier', 'batches.product'])]);
    }

    public function update(Request $request, Lot $lot): JsonResponse
    {
        $this->authorizeTenant($lot);
        $lot->update($request->only(['lot_number', 'received_date', 'quantity']));
        return response()->json(['success' => true, 'data' => $lot->fresh()]);
    }

    public function destroy(Lot $lot): JsonResponse
    {
        $this->authorizeTenant($lot);
        if ($lot->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Only pending lots can be deleted.'], 422);
        }
        $lot->delete();
        return response()->json(['success' => true, 'message' => 'Lot deleted.']);
    }

    public function approveQC(Request $request, Lot $lot): JsonResponse
    {
        $this->authorizeTenant($lot);
        $request->validate(['notes' => 'nullable|string']);
        $lot = $this->service->approveQC($lot->id, $request->notes ?? '');
        return response()->json(['success' => true, 'data' => $lot, 'message' => 'QC approved. Stock made available.']);
    }

    public function rejectQC(Request $request, Lot $lot): JsonResponse
    {
        $this->authorizeTenant($lot);
        $request->validate(['notes' => 'required|string']);
        $lot = $this->service->rejectQC($lot->id, $request->notes);
        return response()->json(['success' => true, 'data' => $lot, 'message' => 'QC rejected. Lot quarantined.']);
    }

    public function trace(Lot $lot): JsonResponse
    {
        $this->authorizeTenant($lot);
        $trace = $this->service->getLotTrace($lot->id);
        return response()->json(['success' => true, 'data' => $trace]);
    }

    private function authorizeTenant(Lot $lot): void
    {
        abort_if($lot->tenant_id !== auth()->user()->tenant_id, 403);
    }
}
