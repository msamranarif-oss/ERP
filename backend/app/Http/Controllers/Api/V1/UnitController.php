<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Http\Requests\Inventory\StoreUnitRequest;
use App\Http\Requests\Inventory\UpdateUnitRequest;
use App\Http\Resources\UnitResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UnitController extends ApiController
{
    public function __construct()
    {
        $this->authorizeResource(Unit::class, 'unit');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Unit::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('abbreviation', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->filled('is_base')) {
            $query->where('is_base', $request->is_base);
        }

        $units = $query->orderBy('name')->paginate($request->per_page ?? 15);

        return UnitResource::collection($units);
    }

    public function store(StoreUnitRequest $request): UnitResource
    {
        $unit = Unit::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
        ]);

        return new UnitResource($unit);
    }

    public function show(Unit $unit): UnitResource
    {
        return new UnitResource($unit);
    }

    public function update(UpdateUnitRequest $request, Unit $unit): UnitResource
    {
        $unit->update($request->validated());

        return new UnitResource($unit);
    }

    public function destroy(Unit $unit): JsonResponse
    {
        if ($unit->products()->exists() || $unit->fromConversions()->exists() || $unit->toConversions()->exists()) {
            return $this->errorResponse('Cannot delete unit that is used in products or conversions.', 422);
        }

        $unit->delete();

        return $this->successResponse(null, 'Unit deleted successfully.');
    }

    public function conversions(Unit $unit): JsonResponse
    {
        $this->authorize('view', $unit);
        $conversions = UnitConversion::where('from_unit_id', $unit->id)
                                    ->with(['toUnit'])
                                    ->get();

        return $this->successResponse($conversions);
    }

    public function addConversion(Request $request, Unit $unit): JsonResponse
    {
        $this->authorize('update', $unit);
        $validated = $request->validate([
            'to_unit_id' => 'required|exists:units,id',
            'conversion_factor' => 'required|numeric|min:0.000001',
        ]);

        $existing = UnitConversion::where('from_unit_id', $unit->id)
                                 ->where('to_unit_id', $validated['to_unit_id'])
                                 ->first();

        if ($existing) {
            return $this->errorResponse('Conversion already exists.', 422);
        }

        $conversion = UnitConversion::create([
            'from_unit_id' => $unit->id,
            'to_unit_id' => $validated['to_unit_id'],
            'conversion_factor' => $validated['conversion_factor'],
            'tenant_id' => $request->user()->tenant_id,
        ]);

        return $this->successResponse($conversion->load(['toUnit']), 'Conversion added successfully.', 201);
    }

    public function removeConversion(Unit $unit, UnitConversion $conversion): JsonResponse
    {
        $this->authorize('update', $unit);
        
        if ($conversion->from_unit_id !== $unit->id) {
            return $this->errorResponse('Invalid conversion.', 422);
        }

        $conversion->delete();

        return $this->successResponse(null, 'Conversion removed successfully.');
    }
}
