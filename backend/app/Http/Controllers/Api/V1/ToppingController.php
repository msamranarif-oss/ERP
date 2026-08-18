<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Topping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ToppingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Authentication required', 401);
        }

        $query = Topping::with(['category', 'unit'])->where('tenant_id', $user->tenant_id);

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->get('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->get('category_id'));
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', '%' . $search . '%')
                  ->orWhere('description', 'LIKE', '%' . $search . '%');
            });
        }

        $toppings = $query->orderBy('sort_order', 'asc')->paginate($request->get('per_page', 15));

        return $this->successResponse([
            'data' => $toppings->items(),
            'pagination' => [
                'current_page' => $toppings->currentPage(),
                'last_page' => $toppings->lastPage(),
                'per_page' => $toppings->perPage(),
                'total' => $toppings->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Authentication required', 401);
        }

        $topping = Topping::with(['category', 'unit'])
            ->where('tenant_id', $user->tenant_id)
            ->findOrFail($id);

        return $this->successResponse($topping);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Authentication required', 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'unit_id' => 'nullable|exists:units,id',
            'cost_per_unit' => 'nullable|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'is_available' => 'boolean',
            'image_url' => 'nullable|string|url',
            'max_allowed' => 'nullable|integer|min:1',
            'min_required' => 'nullable|integer|min:0',
            'sort_order' => 'integer',
        ]);

        $validated['tenant_id'] = $user->tenant_id;

        $topping = Topping::create($validated);

        return $this->successResponse(
            $topping->load(['category', 'unit']),
            'Topping created successfully.',
            201
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Authentication required', 401);
        }

        $topping = Topping::where('tenant_id', $user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'unit_id' => 'nullable|exists:units,id',
            'cost_per_unit' => 'nullable|numeric|min:0',
            'selling_price' => 'sometimes|numeric|min:0',
            'is_active' => 'boolean',
            'is_available' => 'boolean',
            'image_url' => 'nullable|string|url',
            'max_allowed' => 'nullable|integer|min:1',
            'min_required' => 'nullable|integer|min:0',
            'sort_order' => 'integer',
        ]);

        $topping->update($validated);

        return $this->successResponse(
            $topping->fresh(['category', 'unit']),
            'Topping updated successfully.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Authentication required', 401);
        }

        $topping = Topping::where('tenant_id', $user->tenant_id)->findOrFail($id);
        $topping->delete();

        return $this->successResponse(null, 'Topping deleted successfully.');
    }
}
