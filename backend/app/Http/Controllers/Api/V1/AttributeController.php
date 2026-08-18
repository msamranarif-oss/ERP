<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AttributeGroup;
use App\Models\AttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttributeController extends ApiController
{
    // ---- Attribute Groups ----

    public function index(Request $request): JsonResponse
    {
        $groups = AttributeGroup::where('tenant_id', $request->user()->tenant_id)
            ->with('values')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->successResponse($groups);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:50',
            'sort_order' => 'integer|min:0',
        ]);

        $data['tenant_id'] = $request->user()->tenant_id;

        $group = AttributeGroup::create($data);

        return $this->successResponse($group, 'Attribute group created', 201);
    }

    public function show(Request $request, AttributeGroup $attributeGroup): JsonResponse
    {
        abort_if($attributeGroup->tenant_id !== $request->user()->tenant_id, 403);
        return $this->successResponse($attributeGroup->load('values'));
    }

    public function update(Request $request, AttributeGroup $attributeGroup): JsonResponse
    {
        abort_if($attributeGroup->tenant_id !== $request->user()->tenant_id, 403);

        $data = $request->validate([
            'name'       => 'sometimes|string|max:50',
            'sort_order' => 'integer|min:0',
        ]);

        $attributeGroup->update($data);

        return $this->successResponse($attributeGroup->fresh());
    }

    public function destroy(Request $request, AttributeGroup $attributeGroup): JsonResponse
    {
        abort_if($attributeGroup->tenant_id !== $request->user()->tenant_id, 403);
        $attributeGroup->delete();
        return $this->successResponse(null, 'Attribute group deleted');
    }

    // ---- Attribute Values ----

    public function values(Request $request, AttributeGroup $group): JsonResponse
    {
        abort_if($group->tenant_id !== $request->user()->tenant_id, 403);
        return $this->successResponse($group->values()->orderBy('sort_order')->get());
    }

    public function storeValue(Request $request, AttributeGroup $group): JsonResponse
    {
        abort_if($group->tenant_id !== $request->user()->tenant_id, 403);

        $data = $request->validate([
            'value'      => 'required|string|max:100',
            'color_code' => 'nullable|string|max:10',
            'sort_order' => 'integer|min:0',
        ]);

        $data['tenant_id']          = $request->user()->tenant_id;
        $data['attribute_group_id'] = $group->id;

        $value = AttributeValue::create($data);

        return $this->successResponse($value, 'Attribute value created', 201);
    }

    public function destroyValue(Request $request, AttributeValue $value): JsonResponse
    {
        abort_if($value->tenant_id !== $request->user()->tenant_id, 403);
        $value->delete();
        return $this->successResponse(null, 'Attribute value deleted');
    }
}
