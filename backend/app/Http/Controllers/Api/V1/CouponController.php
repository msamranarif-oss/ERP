<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Services\DiscountService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CouponController extends Controller
{
    protected DiscountService $discountService;

    public function __construct(DiscountService $discountService)
    {
        $this->discountService = $discountService;
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Coupon::class);

        $coupons = Coupon::with('categories')->where('tenant_id', auth()->user()->tenant_id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json(['success' => true, 'data' => $coupons]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Coupon::class);

        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
        ]);

        $validated['tenant_id'] = auth()->user()->tenant_id;
        $validated['created_by'] = auth()->id();

        // Check uniqueness for tenant
        if (Coupon::where('tenant_id', $validated['tenant_id'])->where('code', $validated['code'])->exists()) {
            return response()->json(['success' => false, 'message' => 'Coupon code already exists for this tenant.'], 422);
        }

        $coupon = Coupon::create($validated);

        if ($request->has('category_ids')) {
            $coupon->categories()->sync($request->category_ids);
        }

        return response()->json(['success' => true, 'data' => $coupon->load('categories'), 'message' => 'Coupon created successfully.']);
    }

    public function show(Coupon $coupon): JsonResponse
    {
        $this->authorize('view', $coupon);
        return response()->json(['success' => true, 'data' => $coupon->load('categories')]);
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $this->authorize('update', $coupon);

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50',
            'description' => 'nullable|string|max:255',
            'type' => 'sometimes|required|in:percentage,fixed',
            'value' => 'sometimes|required|numeric|min:0',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
        ]);

        if (isset($validated['code']) && $validated['code'] !== $coupon->code) {
             if (Coupon::where('tenant_id', $coupon->tenant_id)->where('code', $validated['code'])->where('id', '!=', $coupon->id)->exists()) {
                return response()->json(['success' => false, 'message' => 'Coupon code already exists.'], 422);
            }
        }

        $coupon->update($validated);

        if ($request->has('category_ids')) {
            $coupon->categories()->sync($request->category_ids);
        }

        return response()->json(['success' => true, 'data' => $coupon->load('categories'), 'message' => 'Coupon updated successfully.']);
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $this->authorize('delete', $coupon);
        $coupon->delete();
        return response()->json(['success' => true, 'message' => 'Coupon deleted successfully.']);
    }

    /**
     * Validate a coupon code for POS.
     */
    public function validateCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'total_amount' => 'required|numeric|min:0',
            'items' => 'required|array',
        ]);

        try {
            $coupon = $this->discountService->validateCoupon($request->code, (float)$request->total_amount, $request->items);
            $discountAmount = $this->discountService->calculateDiscount($coupon, (float)$request->total_amount, $request->items);

            return response()->json([
                'success' => true,
                'data' => [
                    'coupon' => $coupon,
                    'discount_amount' => $discountAmount,
                    'new_total' => (float)$request->total_amount - $discountAmount
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
