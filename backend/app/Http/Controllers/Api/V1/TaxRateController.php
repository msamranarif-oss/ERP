<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Models\TaxRate;
use App\Http\Requests\TaxRateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaxRateController extends ApiController
{
    public function __construct()
    {
        $this->authorizeResource(TaxRate::class, 'tax_rate');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TaxRate::where('tenant_id', Auth::user()->tenant_id);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%')
                  ->orWhere('rate', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->filled('is_default')) {
            $query->where('is_default', $request->is_default);
        }

        $taxRates = $query->orderBy('name')
                         ->paginate($request->per_page ?? 15);

        return $this->successResponse($taxRates, 'Tax rates retrieved successfully');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TaxRateRequest $request): JsonResponse
    {
        $taxRate = TaxRate::create($request->validated());

        return $this->successResponse($taxRate, 'Tax rate created successfully.', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(TaxRate $tax_rate): JsonResponse
    {
        $this->authorize('view', $tax_rate);

        return $this->successResponse($tax_rate, 'Tax rate retrieved successfully');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(TaxRateRequest $request, TaxRate $tax_rate): JsonResponse
    {
        $this->authorize('update', $tax_rate);

        $tax_rate->update($request->validated());

        return $this->successResponse($tax_rate, 'Tax rate updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TaxRate $tax_rate): JsonResponse
    {
        $this->authorize('delete', $tax_rate);

        $tax_rate->delete();

        return $this->successResponse(null, 'Tax rate deleted successfully.');
    }

    /**
     * Set a tax rate as default
     */
    public function setDefault(TaxRate $tax_rate): JsonResponse
    {
        $this->authorize('update', $tax_rate);

        // Unset current default
        TaxRate::where('tenant_id', $tax_rate->tenant_id)
               ->where('is_default', true)
               ->update(['is_default' => false]);

        // Set this as default
        $tax_rate->update(['is_default' => true]);

        return $this->successResponse($tax_rate, 'Tax rate set as default successfully.');
    }
}