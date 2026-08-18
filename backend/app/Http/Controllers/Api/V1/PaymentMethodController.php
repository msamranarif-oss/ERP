<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PaymentMethodController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(PaymentMethod::class, 'payment_method');
    }


    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PaymentMethod::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $methods = $query->orderBy('name')
                          ->paginate($request->per_page ?? 15);

        return \App\Http\Resources\PaymentMethodResource::collection($methods);
    }

    public function store(Request $request): \App\Http\Resources\PaymentMethodResource
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:payment_methods,code,NULL,id,tenant_id,' . auth()->user()->tenant_id,
            'type' => 'required|in:cash,bank_card,mobile_money,cheque,credit',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'account_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        // If setting as default, unset other defaults for this tenant
        if ($request->is_default) {
            PaymentMethod::where('tenant_id', auth()->user()->tenant_id)
                         ->where('is_default', true)
                         ->update(['is_default' => false]);
        }

        $method = PaymentMethod::create([
            ...$validated,
            'tenant_id' => auth()->user()->tenant_id,
        ]);

        return new \App\Http\Resources\PaymentMethodResource($method);
    }

    public function show(PaymentMethod $payment_method): \App\Http\Resources\PaymentMethodResource
    {
        return new \App\Http\Resources\PaymentMethodResource($payment_method);
    }

    public function update(Request $request, PaymentMethod $payment_method): \App\Http\Resources\PaymentMethodResource
    {
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('payment_methods')->ignore($payment_method->id)->where(function ($query) {
                    return $query->where('tenant_id', auth()->user()->tenant_id);
                })
            ],
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('payment_methods')->ignore($payment_method->id)->where(function ($query) {
                    return $query->where('tenant_id', auth()->user()->tenant_id);
                })
            ],
            'type' => 'sometimes|required|in:cash,bank_card,mobile_money,cheque,credit',
            'is_active' => 'sometimes|boolean',
            'is_default' => 'sometimes|boolean',
            'account_number' => 'sometimes|nullable|string|max:255',
            'notes' => 'sometimes|nullable|string|max:1000',
        ]);

        // If setting as default, unset other defaults for this tenant
        if ($request->is_default ?? false) {
            PaymentMethod::where('tenant_id', auth()->user()->tenant_id)
                         ->where('is_default', true)
                         ->where('id', '!=', $payment_method->id)
                         ->update(['is_default' => false]);
        }

        $payment_method->update($validated);

        return new \App\Http\Resources\PaymentMethodResource($payment_method);
    }

    public function destroy(PaymentMethod $payment_method): JsonResponse
    {
        // Fix 8: Use salePayments() which is the actual HasMany relationship on PaymentMethod
        if ($payment_method->salePayments()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete payment method that is used in transactions.',
            ], 422);
        }

        $payment_method->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payment method deleted successfully.',
        ]);
    }
}