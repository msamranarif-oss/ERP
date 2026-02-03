<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\RegisterSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CashRegisterController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CashRegister::with(['branch']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $registers = $query->orderBy('name')
                             ->paginate($request->per_page ?? 15);

        return \App\Http\Resources\CashRegisterResource::collection($registers);
    }

    public function store(Request $request): \App\Http\Resources\CashRegisterResource
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:cash_registers,code,NULL,id,tenant_id,' . auth()->user()->tenant_id,
            'branch_id' => 'nullable|exists:branches,id',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $register = CashRegister::create([
            ...$validated,
            'tenant_id' => auth()->user()->tenant_id,
        ]);

        return new \App\Http\Resources\CashRegisterResource($register);
    }

    public function show(CashRegister $cash_register): \App\Http\Resources\CashRegisterResource
    {
        return new \App\Http\Resources\CashRegisterResource($cash_register->load(['branch']));
    }

    public function update(Request $request, CashRegister $cash_register): \App\Http\Resources\CashRegisterResource
    {
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('cash_registers')->ignore($cash_register->id)->where(function ($query) {
                    return $query->where('tenant_id', auth()->user()->tenant_id);
                })
            ],
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('cash_registers')->ignore($cash_register->id)->where(function ($query) {
                    return $query->where('tenant_id', auth()->user()->tenant_id);
                })
            ],
            'branch_id' => 'sometimes|nullable|exists:branches,id',
            'description' => 'sometimes|nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
        ]);

        $cash_register->update($validated);

        return new \App\Http\Resources\CashRegisterResource($cash_register);
    }

    public function destroy(CashRegister $cash_register): JsonResponse
    {
        // Prevent deletion if register is in use
        if ($cash_register->sessions()->where('closed_at', null)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete cash register that has an open session.',
            ], 422);
        }

        $cash_register->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cash register deleted successfully.',
        ]);
    }
}