<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\UnitConversion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant');
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

        return \App\Http\Resources\UnitResource::collection($units);
    }

    public function store(Request $request): \App\Http\Resources\UnitResource
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'abbreviation' => 'required|string|max:20|unique:units,abbreviation,NULL,id,tenant_id,' . auth()->user()->tenant_id,
            'is_base' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $unit = Unit::create([
            ...$validated,
            'tenant_id' => auth()->user()->tenant_id,
        ]);

        return new \App\Http\Resources\UnitResource($unit);
    }

    public function show(Unit $unit): \App\Http\Resources\UnitResource
    {
        return new \App\Http\Resources\UnitResource($unit);
    }

    public function update(Request $request, Unit $unit): \App\Http\Resources\UnitResource
    {
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('units')->ignore($unit->id)->where(function ($query) {
                    return $query->where('tenant_id', auth()->user()->tenant_id);
                })
            ],
            'abbreviation' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('units')->ignore($unit->id)->where(function ($query) {
                    return $query->where('tenant_id', auth()->user()->tenant_id);
                })
            ],
            'is_base' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $unit->update($validated);

        return new \App\Http\Resources\UnitResource($unit);
    }

    public function destroy(Unit $unit): JsonResponse
    {
        // Prevent deletion if unit has conversions or is used in products
        if ($unit->products()->exists() || $unit->fromConversions()->exists() || $unit->toConversions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete unit that is used in products or conversions.'
            ], 422);
        }

        $unit->delete();

        return response()->json([
            'success' => true,
            'message' => 'Unit deleted successfully.'
        ]);
    }

    public function conversions(Unit $unit)
    {
        $conversions = UnitConversion::where('from_unit_id', $unit->id)
                                    ->with(['toUnit'])
                                    ->get();

        return response()->json([
            'success' => true,
            'data' => $conversions
        ]);
    }

    public function addConversion(Request $request, Unit $unit)
    {
        $validated = $request->validate([
            'to_unit_id' => 'required|exists:units,id',
            'conversion_factor' => 'required|numeric|min:0.000001',
        ]);

        // Check if conversion already exists
        $existing = UnitConversion::where('from_unit_id', $unit->id)
                                 ->where('to_unit_id', $validated['to_unit_id'])
                                 ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Conversion already exists.'
            ], 422);
        }

        $conversion = UnitConversion::create([
            'from_unit_id' => $unit->id,
            'to_unit_id' => $validated['to_unit_id'],
            'conversion_factor' => $validated['conversion_factor'],
            'tenant_id' => auth()->user()->tenant_id,
        ]);

        return response()->json([
            'success' => true,
            'data' => $conversion->load(['toUnit']),
            'message' => 'Conversion added successfully.'
        ], 201);
    }

    public function removeConversion(Unit $unit, UnitConversion $conversion)
    {
        if ($conversion->from_unit_id !== $unit->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid conversion.'
            ], 422);
        }

        $conversion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conversion removed successfully.'
        ]);
    }
}
