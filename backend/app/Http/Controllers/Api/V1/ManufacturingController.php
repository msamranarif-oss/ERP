<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Models\ManufacturingOrder;
use App\Services\ManufacturingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ManufacturingController extends ApiController
{
    protected ManufacturingService $manufacturingService;

    public function __construct(ManufacturingService $manufacturingService)
    {
        $this->manufacturingService = $manufacturingService;
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        $query = ManufacturingOrder::with(['product', 'warehouse', 'items.product'])
            ->where('tenant_id', auth()->user()->tenant_id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $orders = $query->latest()->paginate($request->per_page ?? 15);

        return $this->successResponse($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'   => 'required|exists:products,id',
            'quantity'     => 'required|numeric|min:0.0001',
            'warehouse_id' => 'required|exists:warehouses,id',
            'branch_id'    => 'required|exists:branches,id',
        ]);

        try {
            $mo = $this->manufacturingService->createFromBOM(
                $validated['product_id'],
                $validated['quantity'],
                $validated['warehouse_id'],
                $validated['branch_id']
            );

            return $this->successResponse($mo, 'Manufacturing Order created successfully.', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function show(ManufacturingOrder $order): JsonResponse
    {
        abort_if($order->tenant_id !== auth()->user()->tenant_id, 403);

        return $this->successResponse(
            $order->load(['product', 'warehouse', 'items.product'])
        );
    }

    public function update(ManufacturingOrder $order, Request $request): JsonResponse
    {
        abort_if($order->tenant_id !== auth()->user()->tenant_id, 403);

        if (!in_array($order->status, ['planned'])) {
            return $this->errorResponse('Only planned orders can be edited.', 422);
        }

        $validated = $request->validate([
            'quantity'     => 'sometimes|numeric|min:0.0001',
            'warehouse_id' => 'sometimes|exists:warehouses,id',
            'notes'        => 'nullable|string|max:500',
        ]);

        $order->update($validated);

        return $this->successResponse($order->fresh()->load(['product', 'warehouse', 'items.product']), 'Manufacturing Order updated successfully.');
    }

    public function destroy(ManufacturingOrder $order): JsonResponse
    {
        abort_if($order->tenant_id !== auth()->user()->tenant_id, 403);

        if (!in_array($order->status, ['planned'])) {
            return $this->errorResponse('Only planned orders that have not started can be deleted.', 422);
        }

        $order->items()->delete();
        $order->delete();

        return $this->successResponse(null, 'Manufacturing Order deleted.');
    }

    public function start(ManufacturingOrder $order): JsonResponse
    {
        abort_if($order->tenant_id !== auth()->user()->tenant_id, 403);

        try {
            $this->manufacturingService->startProduction($order);
            return $this->successResponse(
                $order->fresh()->load(['product', 'items.product']),
                'Production started. Raw materials consumed.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function complete(ManufacturingOrder $order, Request $request): JsonResponse
    {
        abort_if($order->tenant_id !== auth()->user()->tenant_id, 403);

        $validated = $request->validate([
            'actual_produced' => 'nullable|numeric|min:0',
        ]);

        try {
            $this->manufacturingService->completeProduction($order, $validated['actual_produced'] ?? null);
            return $this->successResponse(
                $order->fresh()->load(['product', 'items.product']),
                'Production completed. Finished goods added to stock.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function cancel(ManufacturingOrder $order): JsonResponse
    {
        abort_if($order->tenant_id !== auth()->user()->tenant_id, 403);

        if ($order->status === 'completed') {
            return $this->errorResponse('Completed orders cannot be cancelled.', 422);
        }

        $order->update(['status' => 'cancelled']);

        return $this->successResponse(
            $order->fresh(),
            'Manufacturing Order cancelled.'
        );
    }
}
