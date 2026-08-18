<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FiscalYear;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class FiscalYearController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(FiscalYear::class, 'fiscal_year');
    }
   

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = FiscalYear::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('year', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $fiscalYears = $query->orderBy('start_date', 'desc')
                              ->paginate($request->per_page ?? 15);

        return \App\Http\Resources\FiscalYearResource::collection($fiscalYears);
    }

    public function store(Request $request): \App\Http\Resources\FiscalYearResource
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'year' => 'required|integer|min:1900|max:2100|unique:fiscal_years,year,NULL,id,tenant_id,' . auth()->user()->tenant_id,
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'boolean',
        ]);

        $fiscalYear = FiscalYear::create([
            ...$validated,
            'tenant_id' => auth()->user()->tenant_id,
        ]);

        return new \App\Http\Resources\FiscalYearResource($fiscalYear);
    }

    public function show(FiscalYear $fiscal_year): \App\Http\Resources\FiscalYearResource
    {
        return new \App\Http\Resources\FiscalYearResource($fiscal_year);
    }

    public function update(Request $request, FiscalYear $fiscal_year): \App\Http\Resources\FiscalYearResource
    {
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('fiscal_years')->ignore($fiscal_year->id)->where(function ($query) {
                    return $query->where('tenant_id', auth()->user()->tenant_id);
                })
            ],
            'year' => [
                'sometimes',
                'required',
                'integer',
                'min:1900',
                'max:2100',
                Rule::unique('fiscal_years')->ignore($fiscal_year->id)->where(function ($query) {
                    return $query->where('tenant_id', auth()->user()->tenant_id);
                })
            ],
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'is_active' => 'sometimes|boolean',
        ]);

        $fiscal_year->update($validated);

        return new \App\Http\Resources\FiscalYearResource($fiscal_year);
    }

    public function destroy(FiscalYear $fiscal_year): JsonResponse
    {
        // Prevent deletion if fiscal year has transactions
        if ($fiscal_year->journalEntries()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete fiscal year that has transactions.',
            ], 422);
        }

        $fiscal_year->delete();

        return response()->json([
            'success' => true,
            'message' => 'Fiscal year deleted successfully.',
        ]);
    }

    public function close(FiscalYear $fiscal_year)
    {
        if ($fiscal_year->is_closed) {
            return response()->json([
                'success' => false,
                'message' => 'Fiscal year is already closed.',
            ], 422);
        }

        $fiscal_year->update([
            'is_closed' => true,
            'closed_at' => now(),
            'closed_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $fiscal_year,
            'message' => 'Fiscal year closed successfully.',
        ]);
    }
}