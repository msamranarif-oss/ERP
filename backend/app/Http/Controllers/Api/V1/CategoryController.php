<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $categories = Category::with('children')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->paginate($request->per_page ?? 15);

        return \App\Http\Resources\CategoryResource::collection($categories);
    }

    public function store(Request $request): \App\Http\Resources\CategoryResource
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,NULL,id,tenant_id,' . $request->user()->tenant_id,
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ]);

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;
        
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('categories', 'public');
            $data['image'] = $path;
        }

        $category = Category::create($data);

        return new \App\Http\Resources\CategoryResource($category);
    }

    public function show(Category $category): \App\Http\Resources\CategoryResource
    {
        return new \App\Http\Resources\CategoryResource($category->load('parent', 'children'));
    }

    public function update(Request $request, Category $category): \App\Http\Resources\CategoryResource
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:categories,name,' . $category->id . ',id,tenant_id,' . $request->user()->tenant_id,
            'parent_id' => 'sometimes|nullable|exists:categories,id',
            'description' => 'sometimes|nullable|string',
            'image' => 'sometimes|nullable|image|max:2048',
            'is_active' => 'sometimes|boolean',
        ]);

        $data = $request->all();
        
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('categories', 'public');
            $data['image'] = $path;
        }

        $category->update($data);

        return new \App\Http\Resources\CategoryResource($category->load('parent', 'children'));
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->children()->exists() || $category->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with children or products',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }
}