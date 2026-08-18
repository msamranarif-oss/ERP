<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

class BranchController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $branches = Branch::where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->paginate($request->per_page ?? 15);

        return \App\Http\Resources\BranchResource::collection($branches);
    }

    public function store(Request $request): \App\Http\Resources\BranchResource
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:branches,code,NULL,id,tenant_id,' . $request->user()->tenant_id,
            'address' => 'required|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'is_active' => 'boolean',
            'manager_name' => 'nullable|string|max:255',
            'manager_phone' => 'nullable|string|max:20',
        ]);

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;

        $branch = Branch::create($data);

        return new \App\Http\Resources\BranchResource($branch);
    }

    public function show(Branch $branch): \App\Http\Resources\BranchResource
    {
        return new \App\Http\Resources\BranchResource($branch);
    }

    public function update(Request $request, Branch $branch): \App\Http\Resources\BranchResource
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:50|unique:branches,code,' . $branch->id . ',id,tenant_id,' . $request->user()->tenant_id,
            'address' => 'sometimes|required|string',
            'phone' => 'sometimes|nullable|string|max:20',
            'email' => 'sometimes|nullable|email',
            'is_active' => 'sometimes|boolean',
            'manager_name' => 'sometimes|nullable|string|max:255',
            'manager_phone' => 'sometimes|nullable|string|max:20',
        ]);

        $branch->update($request->all());

        return new \App\Http\Resources\BranchResource($branch);
    }

    public function destroy(Branch $branch): JsonResponse
    {
        $this->authorizeForTenant($branch);
        
        $currentUser = request()->user();
        // Only admins can delete branches
        if (!$currentUser->hasRole(['admin', 'super-admin'])) {
            abort(403, 'Unauthorized to delete branches');
        }

        // Check if branch has related records before deletion
        if ($branch->users()->count() > 0 || $branch->warehouses()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete branch that has associated users or warehouses',
            ], 400);
        }

        $branch->delete();

        return response()->json([
            'success' => true,
            'message' => 'Branch deleted successfully',
        ]);
    }

    private function authorizeForTenant($model)
    {
        if ($model->tenant_id !== request()->user()->tenant_id) {
            abort(403, 'Unauthorized to access this resource');
        }
    }
}