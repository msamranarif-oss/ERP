<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $roles = Role::where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->paginate($request->per_page ?? 15);

        return \App\Http\Resources\RoleResource::collection($roles);
    }

    public function store(Request $request): \App\Http\Resources\RoleResource
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,NULL,id,tenant_id,' . $request->user()->tenant_id,
            'guard_name' => 'required|string|max:255',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
            'description' => 'nullable|string',
        ]);

        $role = \Spatie\Permission\Models\Role::create([
            'name' => $request->name,
            'guard_name' => $request->guard_name ?? 'sanctum',
            'tenant_id' => $request->user()->tenant_id,
            'description' => $request->description,
        ]);

        if ($request->permissions) {
            $permissions = Permission::whereIn('name', $request->permissions)->get();
            $role->syncPermissions($permissions);
        }

        return new \App\Http\Resources\RoleResource($role->load('permissions'));
    }

    public function show(Role $role): \App\Http\Resources\RoleResource
    {
        $this->authorizeForTenant($role);
        
        $currentUser = request()->user();
        // Only admins can view roles
        if (!$currentUser->hasRole(['admin', 'super-admin'])) {
            abort(403, 'Unauthorized to view roles');
        }

        return new \App\Http\Resources\RoleResource($role->load('permissions'));
    }

    public function update(Request $request, Role $role): \App\Http\Resources\RoleResource
    {
        $this->authorizeForTenant($role);
        
        $currentUser = request()->user();
        // Only super admins can update roles
        if (!$currentUser->hasRole(['super-admin'])) {
            abort(403, 'Unauthorized to update roles');
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:roles,name,' . $role->id . ',id,tenant_id,' . $request->user()->tenant_id,
            'guard_name' => 'sometimes|required|string|max:255',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'sometimes|exists:permissions,name',
            'description' => 'sometimes|nullable|string',
        ]);

        $roleData = $request->only(['name', 'guard_name', 'description']);

        $role->update($roleData);

        if ($request->has('permissions')) {
            $permissions = Permission::whereIn('name', $request->permissions)->get();
            $role->syncPermissions($permissions);
        }

        return new \App\Http\Resources\RoleResource($role->load('permissions'));
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->authorizeForTenant($role);
        
        $currentUser = request()->user();
        // Only super admins can delete roles
        if (!$currentUser->hasRole(['super-admin'])) {
            abort(403, 'Unauthorized to delete roles');
        }

        // Prevent deletion of roles that are assigned to users
        if ($role->users()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete role that is assigned to users',
            ], 400);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully',
        ]);
    }

    private function authorizeForTenant($model)
    {
        if ($model->tenant_id !== request()->user()->tenant_id) {
            abort(403, 'Unauthorized to access this resource');
        }
    }
}