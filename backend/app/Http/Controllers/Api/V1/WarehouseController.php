<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant');
    }

    public function index(Request $request)
    {
        $query = Warehouse::with(['branch'])->query();

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

        return response()->json([
            'success' => true,
            'data' => $warehouses
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:warehouses,code,NULL,id,tenant_id,' . auth()->user()->tenant_id,
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'branch_id' => 'nullable|exists:branches,id',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // If setting as default, unset other defaults for this tenant
        if ($request->is_default) {
            Warehouse::where('tenant_id', auth()->user()->tenant_id)
                     ->where('is_default', true)
                     ->update(['is_default' => false]);
        }

        $warehouse = Warehouse::create($validated);

        return response()->json([
            'success' => true,
            'data' => $warehouse,
            'message' => 'Warehouse created successfully.'
        ], 201);
    }

    public function show(Warehouse $warehouse)
    {
        return response()->json([
            'success' => true,
            'data' => $warehouse->load(['branch'])
        ]);
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('warehouses')->ignore($warehouse->id)->where(function ($query) {
                    return $query->where('tenant_id', auth()->user()->tenant_id);
                })
            ],
            'address' => 'sometimes|nullable|string|max:500',
            'phone' => 'sometimes|nullable|string|max:20',
            'branch_id' => 'sometimes|nullable|exists:branches,id',
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        // If setting as default, unset other defaults for this tenant
        if ($request->is_default ?? false) {
            Warehouse::where('tenant_id', auth()->user()->tenant_id)
                     ->where('is_default', true)
                     ->where('id', '!=', $warehouse->id)
                     ->update(['is_default' => false]);
        }

        $warehouse->update($validated);

        return response()->json([
            'success' => true,
            'data' => $warehouse->load(['branch']),
            'message' => 'Warehouse updated successfully.'
        ]);
    }

    public function destroy(Warehouse $warehouse)
    {
        // Prevent deletion if warehouse has associated products or transactions
        if ($warehouse->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete warehouse with associated products.'
            ], 422);
        }

        $warehouse->delete();

        return response()->json([
            'success' => true,
            'message' => 'Warehouse deleted successfully.'
        ]);
    }
}