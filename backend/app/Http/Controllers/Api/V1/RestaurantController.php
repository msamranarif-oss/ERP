<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Models\RestaurantTable;
use App\Models\HeldSale;
use App\Services\RestaurantService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RestaurantController extends ApiController
{
    protected RestaurantService $restaurantService;

    public function __construct(RestaurantService $restaurantService)
    {
        $this->restaurantService = $restaurantService;
        $this->middleware('auth:sanctum');
    }

    /**
     * List all tables in the current tenant's branches.
     */
    public function tables(Request $request): JsonResponse
    {
        $query = RestaurantTable::with(['heldSales' => function ($q) {
            $q->where('status', 'held')->latest()->limit(1);
        }])->where('tenant_id', auth()->user()->tenant_id);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('area_name')) {
            $query->where('area_name', $request->area_name);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->successResponse($query->orderBy('name')->get());
    }

    /**
     * Get available tables for a branch.
     */
    public function availableTables(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
        ]);

        $tables = $this->restaurantService->getAvailableTables($validated['branch_id']);

        return $this->successResponse($tables);
    }

    /**
     * Get tables grouped by area.
     */
    public function tablesByArea(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'area_name' => 'required|string|max:50',
        ]);

        $tables = $this->restaurantService->getTablesByArea($validated['branch_id'], $validated['area_name']);

        return $this->successResponse($tables);
    }

    /**
     * Create a new table.
     */
    public function storeTable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name'      => 'required|string|max:50',
            'capacity'  => 'required|integer|min:1|max:50',
            'area_name' => 'nullable|string|max:50',
        ]);

        $table = RestaurantTable::create(array_merge($validated, [
            'tenant_id' => auth()->user()->tenant_id,
            'status'    => 'available',
        ]));

        return $this->successResponse($table, 'Table created successfully.', 201);
    }

    /**
     * Update a table's details (name, capacity, area).
     */
    public function updateTable(RestaurantTable $table, Request $request): JsonResponse
    {
        abort_if($table->tenant_id !== auth()->user()->tenant_id, 403);

        $validated = $request->validate([
            'name'      => 'sometimes|string|max:50',
            'capacity'  => 'sometimes|integer|min:1|max:50',
            'area_name' => 'nullable|string|max:50',
        ]);

        $table->update($validated);

        return $this->successResponse($table->fresh(), 'Table updated successfully.');
    }

    /**
     * Delete a table (only if not occupied/billed).
     */
    public function destroyTable(RestaurantTable $table): JsonResponse
    {
        abort_if($table->tenant_id !== auth()->user()->tenant_id, 403);

        if (in_array($table->status, ['occupied', 'billed'])) {
            return $this->errorResponse('Cannot delete a table that is currently occupied or has an open bill.', 422);
        }

        $table->delete();

        return $this->successResponse(null, 'Table deleted successfully.');
    }

    /**
     * Update table status (available / occupied / billed / reserved).
     */
    public function updateTableStatus(RestaurantTable $table, Request $request): JsonResponse
    {
        abort_if($table->tenant_id !== auth()->user()->tenant_id, 403);

        $validated = $request->validate([
            'status' => 'required|in:available,occupied,billed,reserved',
        ]);

        $updatedTable = $this->restaurantService->updateTableStatus($table->id, $validated['status']);

        return $this->successResponse($updatedTable, 'Table status updated successfully.');
    }

    /**
     * Transfer a table's active order to another table.
     */
    public function transferTable(RestaurantTable $table, Request $request): JsonResponse
    {
        abort_if($table->tenant_id !== auth()->user()->tenant_id, 403);

        $validated = $request->validate([
            'target_table_id' => 'required|exists:restaurant_tables,id|different:table',
        ]);

        $targetTable = RestaurantTable::findOrFail($validated['target_table_id']);

        if ($targetTable->status !== 'available') {
            return $this->errorResponse('Target table is not available.', 422);
        }

        if ($table->status !== 'occupied') {
            return $this->errorResponse('Source table has no active order to transfer.', 422);
        }

        $heldSale = HeldSale::where('restaurant_table_id', $table->id)
            ->where('status', 'held')
            ->firstOrFail();

        $heldSale->update(['restaurant_table_id' => $targetTable->id]);
        $table->update(['status' => 'available']);
        $targetTable->update(['status' => 'occupied']);

        return $this->successResponse([
            'held_sale'    => $heldSale->fresh()->load('restaurantTable'),
            'source_table' => $table->fresh(),
            'target_table' => $targetTable->fresh(),
        ], 'Order transferred successfully.');
    }

    /**
     * Open a table (Dine-in). Creates a HeldSale.
     */
    public function openTable(RestaurantTable $table, Request $request): JsonResponse
    {
        abort_if($table->tenant_id !== auth()->user()->tenant_id, 403);

        if ($table->status !== 'available') {
            return $this->errorResponse('Table is not available.', 422);
        }

        $validated = $request->validate([
            'register_session_id' => 'required|exists:register_sessions,id',
            'customer_id'         => 'nullable|exists:customers,id',
        ]);

        try {
            $heldSale = $this->restaurantService->openTable(
                $table->id,
                $validated['register_session_id'],
                $validated['customer_id'] ?? null
            );

            return $this->successResponse(
                $heldSale->load('restaurantTable'),
                'Table opened successfully.',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to open table: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Close table and generate bill.
     */
    public function closeTable(RestaurantTable $table): JsonResponse
    {
        abort_if($table->tenant_id !== auth()->user()->tenant_id, 403);

        try {
            $sale = $this->restaurantService->closeTable($table->id);

            return $this->successResponse(
                $sale->load(['items.product', 'restaurantTable']),
                'Table closed and bill generated successfully.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to close table: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generate KOT (Kitchen Order Ticket).
     */
    public function generateKOT(HeldSale $held_sale): JsonResponse
    {
        abort_if($held_sale->tenant_id !== auth()->user()->tenant_id, 403);

        try {
            $kotData = $this->restaurantService->generateKOT($held_sale->id);

            return $this->successResponse($kotData, 'KOT generated successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to generate KOT: ' . $e->getMessage(), 422);
        }
    }

    /**
     * Get pending kitchen orders for a branch.
     */
    public function pendingOrders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
        ]);

        $orders = $this->restaurantService->getPendingOrders($validated['branch_id']);

        return $this->successResponse($orders);
    }

    /**
     * Get table turnover statistics.
     */
    public function tableStats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'days'      => 'nullable|integer|min:1|max:365',
        ]);

        $stats = $this->restaurantService->getTableTurnoverStats(
            $validated['branch_id'],
            $validated['days'] ?? 30
        );

        return $this->successResponse($stats);
    }
}
