<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BrandController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $brands = Brand::where('tenant_id', $tenantId)
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->get('per_page', 15));

        return $this->successResponse($brands);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'image'       => 'nullable|string',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        $data['tenant_id'] = $request->user()->tenant_id;
        $data['slug']      = $this->uniqueSlug($data['name'], $data['tenant_id']);

        $brand = Brand::create($data);

        return $this->successResponse($brand, 'Brand created', 201);
    }

    public function show(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeTenant($brand, $request->user());
        return $this->successResponse($brand);
    }

    public function update(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeTenant($brand, $request->user());

        $data = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'image'       => 'nullable|string',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        if (isset($data['name'])) {
            $data['slug'] = $this->uniqueSlug($data['name'], $brand->tenant_id, $brand->id);
        }

        $brand->update($data);

        return $this->successResponse($brand->fresh());
    }

    public function destroy(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeTenant($brand, $request->user());
        $brand->delete();
        return $this->successResponse(null, 'Brand deleted');
    }

    // ---- Helpers ----

    private function authorizeTenant(Brand $brand, $user): void
    {
        abort_if($brand->tenant_id !== $user->tenant_id, 403, 'Unauthorized');
    }

    private function uniqueSlug(string $name, int $tenantId, ?int $exceptId = null): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $i = 1;

        while (
            Brand::where('tenant_id', $tenantId)
                ->where('slug', $slug)
                ->when($exceptId, fn($q) => $q->where('id', '!=', $exceptId))
                ->exists()
        ) {
            $slug = $original . '-' . $i++;
        }

        return $slug;
    }
}
