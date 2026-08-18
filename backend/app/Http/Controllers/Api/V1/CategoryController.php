<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Models\Category;
use App\Http\Requests\Inventory\StoreCategoryRequest;
use App\Http\Requests\Inventory\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends ApiController
{
    public function __construct()
    {
        $this->authorizeResource(Category::class, 'category');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $categories = Category::with('children')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->paginate($request->per_page ?? 15);

        return CategoryResource::collection($categories);
    }

    public function store(StoreCategoryRequest $request): CategoryResource
    {
        $data = $request->validated();
        
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('categories', 'public');
            $data['image'] = $path;
        }

        $category = Category::create($data);

        return new CategoryResource($category);
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category->load('parent', 'children'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $data = $request->validated();
        
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('categories', 'public');
            $data['image'] = $path;
        }

        $category->update($data);

        return new CategoryResource($category->load('parent', 'children'));
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->children()->exists() || $category->products()->exists()) {
            return $this->errorResponse('Cannot delete category with children or products', 422);
        }

        $category->delete();

        return $this->successResponse(null, 'Category deleted successfully');
    }
}