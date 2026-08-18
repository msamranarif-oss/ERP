<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmployeeController extends Controller
{
    private function tid(): int { return auth()->user()->tenant_id; }

    public function index(Request $request): JsonResponse
    {
        $q = Employee::where('tenant_id', $this->tid())
            ->with(['department:id,name', 'position:id,title'])
            ->when($request->status,        fn($q, $v) => $q->where('status', $v))
            ->when($request->department_id, fn($q, $v) => $q->where('department_id', $v))
            ->when($request->search,        fn($q, $v) => $q->where('full_name', 'like', "%{$v}%"))
            ->orderBy('full_name');

        $data = $request->boolean('paginate', true)
            ? $q->paginate($request->integer('per_page', 20))
            : $q->get();

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name'       => 'required|string|max:200',
            'first_name'      => 'nullable|string|max:100',
            'last_name'       => 'nullable|string|max:100',
            'employee_number' => 'nullable|string|max:50',
            'department_id'   => 'nullable|exists:departments,id',
            'position_id'     => 'nullable|exists:positions,id',
            'reports_to'      => 'nullable|exists:employees,id',
            'gender'          => 'nullable|in:male,female,other',
            'date_of_birth'   => 'nullable|date',
            'national_id'     => 'nullable|string|max:50',
            'phone'           => 'nullable|string|max:30',
            'email'           => 'nullable|email|max:180',
            'address'         => 'nullable|string|max:500',
            'hire_date'       => 'required|date',
            'contract_type'   => 'nullable|in:full_time,part_time,contract,intern',
            'status'          => 'nullable|in:active,suspended,terminated,on_leave',
            'bank_name'       => 'nullable|string|max:150',
            'bank_account'    => 'nullable|string|max:50',
            'bank_branch'     => 'nullable|string|max:100',
            'tax_id'          => 'nullable|string|max:50',
            'tax_exemption'   => 'nullable|numeric|min:0',
            'work_location'   => 'nullable|string|max:150',
            'notes'           => 'nullable|string|max:1000',
        ]);

        $employee = Employee::create(array_merge($data, ['tenant_id' => $this->tid()]));
        return response()->json(['success' => true, 'data' => $employee->load(['department','position'])], 201);
    }

    public function show(int $id): JsonResponse
    {
        $employee = Employee::where('tenant_id', $this->tid())
            ->with(['department', 'position', 'manager:id,full_name', 'currentSalary.components.component', 'loans'])
            ->findOrFail($id);
        return response()->json(['success' => true, 'data' => $employee]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $employee = Employee::where('tenant_id', $this->tid())->findOrFail($id);
        $data = $request->validate([
            'full_name'       => 'sometimes|string|max:200',
            'first_name'      => 'nullable|string|max:100',
            'last_name'       => 'nullable|string|max:100',
            'employee_number' => 'nullable|string|max:50',
            'department_id'   => 'nullable|exists:departments,id',
            'position_id'     => 'nullable|exists:positions,id',
            'reports_to'      => 'nullable|exists:employees,id',
            'gender'          => 'nullable|in:male,female,other',
            'date_of_birth'   => 'nullable|date',
            'national_id'     => 'nullable|string|max:50',
            'phone'           => 'nullable|string|max:30',
            'email'           => 'nullable|email|max:180',
            'address'         => 'nullable|string|max:500',
            'hire_date'       => 'sometimes|date',
            'contract_type'   => 'nullable|in:full_time,part_time,contract,intern',
            'status'          => 'nullable|in:active,suspended,terminated,on_leave',
            'bank_name'       => 'nullable|string|max:150',
            'bank_account'    => 'nullable|string|max:50',
            'bank_branch'     => 'nullable|string|max:100',
            'tax_id'          => 'nullable|string|max:50',
            'tax_exemption'   => 'nullable|numeric|min:0',
            'work_location'   => 'nullable|string|max:150',
            'termination_date'=> 'nullable|date',
            'notes'           => 'nullable|string|max:1000',
            'is_active'       => 'nullable|boolean',
        ]);
        $employee->update($data);
        return response()->json(['success' => true, 'data' => $employee->fresh(['department','position'])]);
    }

    public function destroy(int $id): JsonResponse
    {
        Employee::where('tenant_id', $this->tid())->findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Employee deleted.']);
    }

    public function uploadPhoto(Request $request, int $id): JsonResponse
    {
        $request->validate(['photo' => 'required|image|max:2048']);
        $employee = Employee::where('tenant_id', $this->tid())->findOrFail($id);

        if ($employee->photo) Storage::disk('public')->delete($employee->photo);

        $path = $request->file('photo')->store("employees/photos/{$this->tid()}", 'public');
        $employee->update(['photo' => $path]);

        return response()->json(['success' => true, 'photo_url' => Storage::url($path)]);
    }

    public function payslips(int $id): JsonResponse
    {
        $employee = Employee::where('tenant_id', $this->tid())->findOrFail($id);
        $runs = $employee->payrollRuns()
            ->with('period:id,name,start_date,end_date,status')
            ->orderByDesc('created_at')
            ->get(['id','payroll_period_id','gross_earnings','net_pay','tax_amount','status']);
        return response()->json(['success' => true, 'data' => $runs]);
    }
}
