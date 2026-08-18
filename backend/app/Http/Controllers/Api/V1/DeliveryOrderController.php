<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DeliveryOrder::where('tenant_id', auth()->user()->tenant_id)
                              ->with(['sale', 'customer']);

        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('customer_id')) $query->where('customer_id', $request->customer_id);

        return response()->json(['success' => true, 'data' => $query->orderByDesc('created_at')->paginate($request->per_page ?? 15)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sale_id'          => 'required|exists:sales,id',
            'customer_id'      => 'required|exists:customers,id',
            'delivery_address' => 'required|string',
            'scheduled_at'     => 'nullable|date',
            'driver_name'      => 'nullable|string|max:100',
            'vehicle_number'   => 'nullable|string|max:50',
            'notes'            => 'nullable|string',
        ]);

        $data['tenant_id']       = auth()->user()->tenant_id;
        $data['delivery_number'] = 'DEL-' . strtoupper(uniqid());

        $order = DeliveryOrder::create($data);
        return response()->json(['success' => true, 'data' => $order->load(['sale', 'customer']), 'message' => 'Delivery order created.'], 201);
    }

    public function show(DeliveryOrder $deliveryOrder): JsonResponse
    {
        $this->authorizeTenant($deliveryOrder);
        return response()->json(['success' => true, 'data' => $deliveryOrder->load(['sale', 'customer'])]);
    }

    public function update(Request $request, DeliveryOrder $deliveryOrder): JsonResponse
    {
        $this->authorizeTenant($deliveryOrder);
        $deliveryOrder->update($request->validate([
            'delivery_address' => 'sometimes|string',
            'scheduled_at'     => 'nullable|date',
            'driver_name'      => 'nullable|string',
            'vehicle_number'   => 'nullable|string',
            'notes'            => 'nullable|string',
        ]));
        return response()->json(['success' => true, 'data' => $deliveryOrder->fresh()]);
    }

    public function destroy(DeliveryOrder $deliveryOrder): JsonResponse
    {
        $this->authorizeTenant($deliveryOrder);
        if ($deliveryOrder->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Only pending orders can be deleted.'], 422);
        }
        $deliveryOrder->delete();
        return response()->json(['success' => true, 'message' => 'Delivery order deleted.']);
    }

    public function dispatch(DeliveryOrder $deliveryOrder): JsonResponse
    {
        $this->authorizeTenant($deliveryOrder);
        if ($deliveryOrder->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Order must be pending to dispatch.'], 422);
        }
        $deliveryOrder->update(['status' => 'dispatched', 'dispatched_at' => now()]);
        return response()->json(['success' => true, 'data' => $deliveryOrder->fresh(), 'message' => 'Order dispatched.']);
    }

    public function deliver(DeliveryOrder $deliveryOrder): JsonResponse
    {
        $this->authorizeTenant($deliveryOrder);
        if ($deliveryOrder->status !== 'dispatched') {
            return response()->json(['success' => false, 'message' => 'Order must be dispatched first.'], 422);
        }
        $deliveryOrder->update(['status' => 'delivered', 'delivered_at' => now()]);
        return response()->json(['success' => true, 'data' => $deliveryOrder->fresh(), 'message' => 'Order marked as delivered.']);
    }

    private function authorizeTenant(DeliveryOrder $order): void
    {
        abort_if($order->tenant_id !== auth()->user()->tenant_id, 403);
    }
}
