<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    private function tid(): int { return auth()->user()->tenant_id; }

    public function index(): JsonResponse
    {
        $departments = Department::where('tenant_id', $this->tid())
            ->with(['parent:id,name', 'manager:id,full_name'])
            ->withCount('employees')
            ->orderBy('name')
            ->get();
        return ApiResponse::success($departments);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:150',
            'parent_id'   => 'nullable|exists:departments,id',
            'manager_id'  => 'nullable|exists:employees,id',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'nullable|boolean',
        ]);
        $dept = Department::create(array_merge($data, ['tenant_id' => $this->tid()]));
        return ApiResponse::created($dept);
    }

    public function show(int $id): JsonResponse
    {
        $dept = Department::where('tenant_id', $this->tid())->with(['parent','manager','children','positions'])->findOrFail($id);
        return ApiResponse::success($dept);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $dept = Department::where('tenant_id', $this->tid())->findOrFail($id);
        $data = $request->validate([
            'name'        => 'sometimes|string|max:150',
            'parent_id'   => 'nullable|exists:departments,id',
            'manager_id'  => 'nullable|exists:employees,id',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'nullable|boolean',
        ]);
        $dept->update($data);
        return ApiResponse::success($dept);
    }

    public function destroy(int $id): JsonResponse
    {
        $dept = Department::where('tenant_id', $this->tid())->findOrFail($id);
        $dept->delete();
        return ApiResponse::deleted('Department deleted successfully.');
    }
}
