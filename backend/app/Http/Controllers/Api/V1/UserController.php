<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::with(['role', 'branch'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->paginate($request->per_page ?? 15);

        return \App\Http\Resources\UserResource::collection($users);
    }

    public function store(Request $request): \App\Http\Resources\UserResource
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,NULL,id,tenant_id,' . $request->user()->tenant_id,
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'role_id' => 'required|exists:roles,id',
            'branch_id' => 'required|exists:branches,id',
            'is_active' => 'boolean',
        ]);

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;
        $data['password'] = Hash::make($request->password);

        $user = User::create($data);

        return new \App\Http\Resources\UserResource($user->load('role', 'branch'));
    }

    public function show(User $user): \App\Http\Resources\UserResource
    {
        $this->authorizeForTenant($user);
        
        $currentUser = request()->user();
        // Users can view their own profile, admins can view any user
        if ($user->id !== $currentUser->id && !$currentUser->hasRole(['admin', 'super-admin'])) {
            abort(403, 'Unauthorized to view this user');
        }

        return new \App\Http\Resources\UserResource($user->load('role', 'branch'));
    }

    public function update(Request $request, User $user): \App\Http\Resources\UserResource
    {
        $this->authorizeForTenant($user);
        
        $currentUser = request()->user();
        // Users can update their own profile, admins can update any user
        if ($user->id !== $currentUser->id && !$currentUser->hasRole(['admin', 'super-admin'])) {
            abort(403, 'Unauthorized to update this user');
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'email',
                Rule::unique('users')->ignore($user->id)->where(function ($query) use ($request) {
                    return $query->where('tenant_id', $request->user()->tenant_id);
                })
            ],
            'password' => 'sometimes|nullable|string|min:8|confirmed',
            'phone' => 'sometimes|nullable|string|max:20',
            'address' => 'sometimes|nullable|string',
            'role_id' => 'sometimes|required|exists:roles,id',
            'branch_id' => 'sometimes|required|exists:branches,id',
            'is_active' => 'sometimes|boolean',
        ]);

        $data = $request->all();

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return new \App\Http\Resources\UserResource($user->load('role', 'branch'));
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === request()->user()->id) {
            return ApiResponse::error('Cannot delete your own account', 400, [], 'SELF_ACCOUNT_DELETE');
        }

        $user->delete();

        return ApiResponse::deleted('User deleted successfully');
    }
}