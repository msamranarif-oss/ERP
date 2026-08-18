<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Role::class, 'role');
    }

    /**
     * List all available permissions (for role form matrix).
     */
    public function listPermissions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $permissions = Permission::orderBy('name')
            ->get(['id', 'name', 'guard_name'])
            ->map(function ($p) {
                // Derive a module group for the frontend matrix UI
                $name = $p->name;
                $module = match (true) {
                    str_starts_with($name, 'user') => 'Users',
                    str_starts_with($name, 'view-user') || str_starts_with($name, 'create-user') || str_starts_with($name, 'edit-user') || str_starts_with($name, 'delete-user') => 'Users',
                    str_contains($name, 'role') => 'Roles',
                    str_contains($name, 'product') || str_contains($name, 'category') || str_contains($name, 'supplier') || str_contains($name, 'warehouse') || str_contains($name, 'unit') || str_contains($name, 'inventory') => 'Inventory',
                    str_contains($name, 'sale') || str_contains($name, 'customer') || str_contains($name, 'cash') || str_contains($name, 'process-') => 'POS & Customers',
                    str_contains($name, 'credit') || str_contains($name, 'installment') || str_contains($name, 'payment') => 'Credit & Finance',
                    str_contains($name, 'account') || str_contains($name, 'journal') || str_contains($name, 'report') => 'Accounting & Reports',
                    default => 'System',
                };
                $action = match (true) {
                    str_starts_with($name, 'view') => 'View',
                    str_starts_with($name, 'create') => 'Create',
                    str_starts_with($name, 'edit') => 'Edit',
                    str_starts_with($name, 'delete') => 'Delete',
                    str_starts_with($name, 'process') => 'Process',
                    str_starts_with($name, 'manage') => 'Manage',
                    str_starts_with($name, 'post') => 'Post',
                    default => 'Other',
                };

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'guard_name' => $p->guard_name,
                    'module' => $module,
                    'action' => $action,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }

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
            'name' => 'required|string|max:255|unique:roles,name,NULL,id,tenant_id,'.$request->user()->tenant_id,
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
        if (! $currentUser->hasRole(['admin', 'super-admin'])) {
            abort(403, 'Unauthorized to view roles');
        }

        return new \App\Http\Resources\RoleResource($role->load('permissions'));
    }

    public function update(Request $request, Role $role): \App\Http\Resources\RoleResource
    {
        $this->authorizeForTenant($role);

        $currentUser = request()->user();
        // Only super admins can update roles
        if (! $currentUser->hasRole(['super-admin'])) {
            abort(403, 'Unauthorized to update roles');
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:roles,name,'.$role->id.',id,tenant_id,'.$request->user()->tenant_id,
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
        if (! $currentUser->hasRole(['super-admin'])) {
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
