<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\UnitCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitCategoryController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $categories = UnitCategory::where('tenant_id', $tenantId)
            ->withCount('units')
            ->orderBy('name')
            ->get();

        return $this->successResponse($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:50',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        $data['tenant_id'] = $request->user()->tenant_id;

        $category = UnitCategory::create($data);

        return $this->successResponse($category, 'Unit category created', 201);
    }

    public function show(Request $request, UnitCategory $unitCategory): JsonResponse
    {
        abort_if($unitCategory->tenant_id !== $request->user()->tenant_id, 403);
        return $this->successResponse($unitCategory->load('units'));
    }

    public function update(Request $request, UnitCategory $unitCategory): JsonResponse
    {
        abort_if($unitCategory->tenant_id !== $request->user()->tenant_id, 403);

        $data = $request->validate([
            'name'        => 'sometimes|string|max:50',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        $unitCategory->update($data);

        return $this->successResponse($unitCategory->fresh());
    }

    public function destroy(Request $request, UnitCategory $unitCategory): JsonResponse
    {
        abort_if($unitCategory->tenant_id !== $request->user()->tenant_id, 403);
        abort_if($unitCategory->is_system, 422, 'System categories cannot be deleted');

        $unitCategory->delete();

        return $this->successResponse(null, 'Unit category deleted');
    }
}
