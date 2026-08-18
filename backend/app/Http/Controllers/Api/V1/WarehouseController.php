<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Models\Warehouse;
use App\Http\Requests\Inventory\StoreWarehouseRequest;
use App\Http\Requests\Inventory\UpdateWarehouseRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends ApiController
{
    public function __construct()
    {
        $this->authorizeResource(Warehouse::class, 'warehouse');
    }

    public function index(Request $request): JsonResponse
    {
        $query = Warehouse::with(['branch']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%')
                  ->orWhere('phone', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $warehouses = $query->orderBy('name')->paginate($request->per_page ?? 15);

        return $this->successResponse($warehouses);
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tenantId = $request->user()->tenant_id;

        // If setting as default, unset other defaults for this tenant
        if ($request->is_default) {
            Warehouse::where('tenant_id', $tenantId)
                     ->where('is_default', true)
                     ->update(['is_default' => false]);
        }

        $warehouse = Warehouse::create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        return $this->successResponse($warehouse, 'Warehouse created successfully.', 201);
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        return $this->successResponse($warehouse->load(['branch']));
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        $data = $request->validated();
        $tenantId = $request->user()->tenant_id;

        // If setting as default, unset other defaults for this tenant
        if ($request->is_default ?? false) {
            Warehouse::where('tenant_id', $tenantId)
                     ->where('is_default', true)
                     ->where('id', '!=', $warehouse->id)
                     ->update(['is_default' => false]);
        }

        $warehouse->update($data);

        return $this->successResponse($warehouse->load(['branch']), 'Warehouse updated successfully.');
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        if ($warehouse->products()->exists()) {
            return $this->errorResponse('Cannot delete warehouse with associated products.', 422);
        }

        $warehouse->delete();

        return $this->successResponse(null, 'Warehouse deleted successfully.');
    }
}