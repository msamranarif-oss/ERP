<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SalaryComponent;
use App\Models\EmployeeSalary;
use App\Models\EmployeeSalaryComponent;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalaryStructureController extends Controller
{
    private function tid(): int { return auth()->user()->tenant_id; }

    // ── Salary Components ─────────────────────────────────────────────

    public function indexComponents(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => SalaryComponent::where('tenant_id', $this->tid())->orderBy('type')->orderBy('name')->get(),
        ]);
    }

    public function storeComponent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'             => 'required|string|max:150',
            'code'             => 'nullable|string|max:30',
            'type'             => 'required|in:earning,deduction,tax',
            'calculation_type' => 'required|in:fixed,percentage_of_basic,percentage_of_gross',
            'default_value'    => 'required|numeric|min:0',
            'taxable'          => 'nullable|boolean',
            'is_statutory'     => 'nullable|boolean',
            'is_active'        => 'nullable|boolean',
            'description'      => 'nullable|string|max:500',
        ]);
        $comp = SalaryComponent::create(array_merge($data, ['tenant_id' => $this->tid()]));
        return response()->json(['success' => true, 'data' => $comp], 201);
    }

    public function updateComponent(Request $request, int $id): JsonResponse
    {
        $comp = SalaryComponent::where('tenant_id', $this->tid())->findOrFail($id);
        $comp->update($request->validate([
            'name'             => 'sometimes|string|max:150',
            'code'             => 'nullable|string|max:30',
            'type'             => 'sometimes|in:earning,deduction,tax',
            'calculation_type' => 'sometimes|in:fixed,percentage_of_basic,percentage_of_gross',
            'default_value'    => 'sometimes|numeric|min:0',
            'taxable'          => 'nullable|boolean',
            'is_statutory'     => 'nullable|boolean',
            'is_active'        => 'nullable|boolean',
            'description'      => 'nullable|string|max:500',
        ]));
        return response()->json(['success' => true, 'data' => $comp]);
    }

    public function destroyComponent(int $id): JsonResponse
    {
        SalaryComponent::where('tenant_id', $this->tid())->findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Salary component deleted.']);
    }

    // ── Employee Salary Structure ─────────────────────────────────────

    public function getEmployeeSalary(int $employeeId): JsonResponse
    {
        $employee = Employee::where('tenant_id', $this->tid())->findOrFail($employeeId);
        $salary = $employee->currentSalary()->with('components.component')->first();
        return response()->json(['success' => true, 'data' => $salary]);
    }

    public function setEmployeeSalary(Request $request, int $employeeId): JsonResponse
    {
        $employee = Employee::where('tenant_id', $this->tid())->findOrFail($employeeId);

        $data = $request->validate([
            'basic_salary'    => 'required|numeric|min:0',
            'effective_date'  => 'required|date',
            'pay_frequency'   => 'nullable|in:monthly,biweekly,weekly',
            'currency'        => 'nullable|string|max:10',
            'notes'           => 'nullable|string|max:500',
            'components'      => 'nullable|array',
            'components.*.salary_component_id' => 'required|exists:salary_components,id',
            'components.*.value'               => 'required|numeric|min:0',
            'components.*.is_active'           => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($employee, $data) {
            // Mark previous salary as not current
            EmployeeSalary::where('employee_id', $employee->id)->update(['is_current' => false]);

            $salary = EmployeeSalary::create([
                'tenant_id'      => $this->tid(),
                'employee_id'    => $employee->id,
                'basic_salary'   => $data['basic_salary'],
                'effective_date' => $data['effective_date'],
                'pay_frequency'  => $data['pay_frequency'] ?? 'monthly',
                'currency'       => $data['currency'] ?? 'USD',
                'notes'          => $data['notes'] ?? null,
                'is_current'     => true,
            ]);

            foreach ($data['components'] ?? [] as $comp) {
                EmployeeSalaryComponent::create([
                    'employee_salary_id'   => $salary->id,
                    'salary_component_id'  => $comp['salary_component_id'],
                    'value'               => $comp['value'],
                    'is_active'           => $comp['is_active'] ?? true,
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Salary structure saved.',
            'data'    => $employee->fresh()->currentSalary()->with('components.component')->first(),
        ]);
    }
}
