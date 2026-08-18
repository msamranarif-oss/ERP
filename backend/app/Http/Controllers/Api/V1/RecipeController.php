<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecipeRequest;
use App\Http\Requests\UpdateRecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Services\RecipeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RecipeController extends Controller
{
    protected RecipeService $recipeService;

    public function __construct(RecipeService $recipeService)
    {
        $this->recipeService = $recipeService;
    }

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Authentication required', 401);
        }

        $filters = [
            'tenant_id' => $user->tenant_id,
            'is_active' => $request->get('is_active'),
            'category' => $request->get('category'),
            'search' => $request->get('search'),
            'per_page' => $request->get('per_page', 15),
        ];

        $recipes = $this->recipeService->getAllRecipes($filters);

        return $this->successResponse([
            'data' => RecipeResource::collection($recipes),
            'pagination' => [
                'current_page' => $recipes->currentPage(),
                'last_page' => $recipes->lastPage(),
                'per_page' => $recipes->perPage(),
                'total' => $recipes->total(),
                'from' => $recipes->firstItem(),
                'to' => $recipes->lastItem(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $recipe = $this->recipeService->getRecipeById($id);

        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Authentication required', 401);
        }

        // Ensure the recipe belongs to the authenticated user's tenant
        abort_if($recipe->tenant_id !== $user->tenant_id, 403);

        return $this->successResponse(new RecipeResource($recipe));
    }

    public function store(StoreRecipeRequest $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Authentication required', 401);
        }

        $data = $request->validated();
        $data['tenant_id'] = $user->tenant_id;

        $recipe = $this->recipeService->createRecipe($data);

        return $this->successResponse(
            new RecipeResource($recipe),
            'Recipe created successfully.',
            201
        );
    }

    public function update(UpdateRecipeRequest $request, int $id): JsonResponse
    {
        $recipe = $this->recipeService->getRecipeById($id);

        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Authentication required', 401);
        }

        // Ensure the recipe belongs to the authenticated user's tenant
        abort_if($recipe->tenant_id !== $user->tenant_id, 403);

        $data = $request->validated();

        $recipe = $this->recipeService->updateRecipe($id, $data);

        return $this->successResponse(
            new RecipeResource($recipe),
            'Recipe updated successfully.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $recipe = $this->recipeService->getRecipeById($id);

        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Authentication required', 401);
        }

        // Ensure the recipe belongs to the authenticated user's tenant
        abort_if($recipe->tenant_id !== $user->tenant_id, 403);

        $this->recipeService->deleteRecipe($id);

        return $this->successResponse(null, 'Recipe deleted successfully.');
    }

    public function getNutritionInfo(int $id): JsonResponse
    {
        $recipe = $this->recipeService->getRecipeById($id);

        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('Authentication required', 401);
        }

        // Ensure the recipe belongs to the authenticated user's tenant
        abort_if($recipe->tenant_id !== $user->tenant_id, 403);

        $nutritionInfo = $this->recipeService->getNutritionInfo($id);

        return $this->successResponse($nutritionInfo);
    }
}