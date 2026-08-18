<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    private function tid(): int { return auth()->user()->tenant_id; }

    public function index(): JsonResponse
    {
        $positions = Position::where('tenant_id', $this->tid())
            ->with('department:id,name')
            ->withCount('employees')
            ->orderBy('title')
            ->get();
        return response()->json(['success' => true, 'data' => $positions]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'         => 'required|string|max:150',
            'department_id' => 'nullable|exists:departments,id',
            'description'   => 'nullable|string|max:500',
            'min_salary'    => 'nullable|numeric|min:0',
            'max_salary'    => 'nullable|numeric|min:0',
            'is_active'     => 'nullable|boolean',
        ]);
        $pos = Position::create(array_merge($data, ['tenant_id' => $this->tid()]));
        return response()->json(['success' => true, 'data' => $pos], 201);
    }

    public function show(int $id): JsonResponse
    {
        $pos = Position::where('tenant_id', $this->tid())->with('department')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $pos]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $pos = Position::where('tenant_id', $this->tid())->findOrFail($id);
        $pos->update($request->validate([
            'title'         => 'sometimes|string|max:150',
            'department_id' => 'nullable|exists:departments,id',
            'description'   => 'nullable|string|max:500',
            'min_salary'    => 'nullable|numeric|min:0',
            'max_salary'    => 'nullable|numeric|min:0',
            'is_active'     => 'nullable|boolean',
        ]));
        return response()->json(['success' => true, 'data' => $pos]);
    }

    public function destroy(int $id): JsonResponse
    {
        Position::where('tenant_id', $this->tid())->findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Position deleted.']);
    }
}
