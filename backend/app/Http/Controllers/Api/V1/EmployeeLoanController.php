<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EmployeeLoan;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeLoanController extends Controller
{
    private function tid(): int { return auth()->user()->tenant_id; }

    public function index(Request $request): JsonResponse
    {
        $q = EmployeeLoan::where('tenant_id', $this->tid())
            ->with('employee:id,full_name,employee_number')
            ->when($request->employee_id, fn($q, $v) => $q->where('employee_id', $v))
            ->when($request->status,      fn($q, $v) => $q->where('status', $v))
            ->orderByDesc('created_at');
        return response()->json(['success' => true, 'data' => $q->paginate($request->integer('per_page', 20))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id'       => 'required|exists:employees,id',
            'amount'            => 'required|numeric|min:1',
            'monthly_deduction' => 'required|numeric|min:1',
            'start_date'        => 'required|date',
            'end_date'          => 'nullable|date|after:start_date',
            'notes'             => 'nullable|string|max:500',
        ]);

        Employee::where('tenant_id', $this->tid())->findOrFail($data['employee_id']);

        $loan = EmployeeLoan::create(array_merge($data, [
            'tenant_id' => $this->tid(),
            'balance'   => $data['amount'],
            'status'    => 'active',
        ]));

        return response()->json(['success' => true, 'data' => $loan->load('employee:id,full_name')], 201);
    }

    public function show(int $id): JsonResponse
    {
        $loan = EmployeeLoan::where('tenant_id', $this->tid())->with('employee:id,full_name')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $loan]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $loan = EmployeeLoan::where('tenant_id', $this->tid())->findOrFail($id);
        $loan->update($request->validate([
            'monthly_deduction' => 'sometimes|numeric|min:0',
            'end_date'          => 'nullable|date',
            'status'            => 'nullable|in:active,completed,cancelled',
            'notes'             => 'nullable|string|max:500',
        ]));
        return response()->json(['success' => true, 'data' => $loan]);
    }
}
