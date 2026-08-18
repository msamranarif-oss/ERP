<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use App\Models\LeaveRequest;
use App\Models\LeaveBalance;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveController extends Controller
{
    private function tid(): int { return auth()->user()->tenant_id; }

    // ── Leave Types ────────────────────────────────────────────────────

    public function indexTypes(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => LeaveType::where('tenant_id', $this->tid())->get()]);
    }

    public function storeType(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                  => 'required|string|max:100',
            'code'                  => 'nullable|string|max:20',
            'days_allowed_per_year' => 'required|integer|min:0',
            'carry_forward'         => 'nullable|boolean',
            'max_carry_forward_days'=> 'nullable|integer|min:0',
            'is_paid'               => 'nullable|boolean',
            'requires_approval'     => 'nullable|boolean',
            'is_active'             => 'nullable|boolean',
        ]);
        $type = LeaveType::create(array_merge($data, ['tenant_id' => $this->tid()]));
        return response()->json(['success' => true, 'data' => $type], 201);
    }

    public function updateType(Request $request, int $id): JsonResponse
    {
        $type = LeaveType::where('tenant_id', $this->tid())->findOrFail($id);
        $type->update($request->validate([
            'name'                  => 'sometimes|string|max:100',
            'days_allowed_per_year' => 'sometimes|integer|min:0',
            'carry_forward'         => 'nullable|boolean',
            'max_carry_forward_days'=> 'nullable|integer|min:0',
            'is_paid'               => 'nullable|boolean',
            'is_active'             => 'nullable|boolean',
        ]));
        return response()->json(['success' => true, 'data' => $type]);
    }

    public function destroyType(int $id): JsonResponse
    {
        LeaveType::where('tenant_id', $this->tid())->findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Leave type deleted.']);
    }

    // ── Leave Requests ─────────────────────────────────────────────────

    public function indexRequests(Request $request): JsonResponse
    {
        $q = LeaveRequest::where('tenant_id', $this->tid())
            ->with(['employee:id,full_name', 'leaveType:id,name'])
            ->when($request->employee_id, fn($q, $v) => $q->where('employee_id', $v))
            ->when($request->status,      fn($q, $v) => $q->where('status', $v))
            ->orderByDesc('start_date');
        return response()->json(['success' => true, 'data' => $q->paginate($request->integer('per_page', 20))]);
    }

    public function storeRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id'  => 'required|exists:employees,id',
            'leave_type_id'=> 'required|exists:leave_types,id',
            'start_date'   => 'required|date|after_or_equal:today',
            'end_date'     => 'required|date|after_or_equal:start_date',
            'reason'       => 'nullable|string|max:500',
        ]);

        $days = $this->calculateLeaveDays($data['start_date'], $data['end_date']);

        // Check balance
        $year    = date('Y');
        $balance = LeaveBalance::firstOrCreate(
            ['tenant_id' => $this->tid(), 'employee_id' => $data['employee_id'], 'leave_type_id' => $data['leave_type_id'], 'year' => $year],
            ['allocated' => LeaveType::find($data['leave_type_id'])->days_allowed_per_year ?? 0, 'used' => 0, 'pending' => 0]
        );

        $available = ($balance->allocated + $balance->carried_forward) - $balance->used - $balance->pending;
        if ($days > $available) {
            return response()->json(['success' => false, 'message' => "Insufficient leave balance. Available: {$available} days."], 422);
        }

        DB::transaction(function () use ($data, $days, $balance) {
            $leave = LeaveRequest::create(array_merge($data, [
                'tenant_id' => $this->tid(),
                'days'      => $days,
                'status'    => 'pending',
            ]));
            $balance->increment('pending', $days);
        });

        return response()->json(['success' => true, 'message' => 'Leave request submitted.'], 201);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $lr = LeaveRequest::where('tenant_id', $this->tid())->where('status', 'pending')->findOrFail($id);

        DB::transaction(function () use ($lr, $request) {
            $lr->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now(), 'notes' => $request->notes]);
            $balance = LeaveBalance::where('employee_id', $lr->employee_id)->where('leave_type_id', $lr->leave_type_id)->where('year', $lr->start_date->year)->first();
            if ($balance) {
                $balance->decrement('pending', $lr->days);
                $balance->increment('used', $lr->days);
            }
        });

        return response()->json(['success' => true, 'message' => 'Leave approved.']);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $lr = LeaveRequest::where('tenant_id', $this->tid())->where('status', 'pending')->findOrFail($id);

        DB::transaction(function () use ($lr, $request) {
            $lr->update(['status' => 'rejected', 'notes' => $request->notes]);
            $balance = LeaveBalance::where('employee_id', $lr->employee_id)->where('leave_type_id', $lr->leave_type_id)->where('year', $lr->start_date->year)->first();
            if ($balance) $balance->decrement('pending', $lr->days);
        });

        return response()->json(['success' => true, 'message' => 'Leave rejected.']);
    }

    public function employeeBalance(int $employeeId): JsonResponse
    {
        Employee::where('tenant_id', $this->tid())->findOrFail($employeeId);
        $balances = LeaveBalance::where('tenant_id', $this->tid())
            ->where('employee_id', $employeeId)
            ->where('year', date('Y'))
            ->with('leaveType:id,name,code,is_paid')
            ->get()
            ->map(fn($b) => array_merge($b->toArray(), ['available' => $b->available]));
        return response()->json(['success' => true, 'data' => $balances]);
    }

    private function calculateLeaveDays(string $start, string $end): float
    {
        $start = \Carbon\Carbon::parse($start);
        $end   = \Carbon\Carbon::parse($end);
        $days  = 0;
        $cur   = $start->copy();
        while ($cur->lte($end)) {
            if (!$cur->isWeekend()) $days++;
            $cur->addDay();
        }
        return $days;
    }
}
