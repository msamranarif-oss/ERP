<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    private function tid(): int { return auth()->user()->tenant_id; }

    public function index(Request $request): JsonResponse
    {
        $q = Attendance::where('tenant_id', $this->tid())
            ->with('employee:id,full_name,employee_number')
            ->when($request->employee_id, fn($q, $v) => $q->where('employee_id', $v))
            ->when($request->date,        fn($q, $v) => $q->where('date', $v))
            ->when($request->month,       fn($q, $v) => $q->whereMonth('date', date('m', strtotime($v)))->whereYear('date', date('Y', strtotime($v))))
            ->when($request->status,      fn($q, $v) => $q->where('status', $v))
            ->orderByDesc('date');

        return response()->json(['success' => true, 'data' => $q->paginate($request->integer('per_page', 50))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id'  => 'required|exists:employees,id',
            'date'         => 'required|date',
            'check_in'     => 'nullable|date_format:H:i',
            'check_out'    => 'nullable|date_format:H:i',
            'status'       => 'required|in:present,absent,late,half_day,leave,holiday,weekend',
            'notes'        => 'nullable|string|max:300',
        ]);

        // Auto-calculate hours worked
        if (!empty($data['check_in']) && !empty($data['check_out'])) {
            $in  = \Carbon\Carbon::parse($data['check_in']);
            $out = \Carbon\Carbon::parse($data['check_out']);
            $data['hours_worked']    = round(max(0, $out->diffInMinutes($in) / 60), 2);
            $data['overtime_hours']  = round(max(0, $data['hours_worked'] - 8), 2);
        }

        $attendance = Attendance::updateOrCreate(
            ['tenant_id' => $this->tid(), 'employee_id' => $data['employee_id'], 'date' => $data['date']],
            $data
        );

        return response()->json(['success' => true, 'data' => $attendance], 201);
    }

    public function bulkStore(Request $request): JsonResponse
    {
        $request->validate([
            'records'                => 'required|array|min:1',
            'records.*.employee_id'  => 'required|exists:employees,id',
            'records.*.date'         => 'required|date',
            'records.*.status'       => 'required|in:present,absent,late,half_day,leave,holiday,weekend',
        ]);

        $tid = $this->tid();
        $inserted = 0;

        DB::transaction(function () use ($request, $tid, &$inserted) {
            foreach ($request->records as $record) {
                Attendance::updateOrCreate(
                    ['tenant_id' => $tid, 'employee_id' => $record['employee_id'], 'date' => $record['date']],
                    array_merge($record, ['tenant_id' => $tid])
                );
                $inserted++;
            }
        });

        return response()->json(['success' => true, 'message' => "{$inserted} attendance records saved."]);
    }

    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'month'       => 'required|date_format:Y-m',
        ]);

        [$year, $month] = explode('-', $request->month);

        $query = Attendance::where('tenant_id', $this->tid())
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->when($request->employee_id, fn($q, $v) => $q->where('employee_id', $v))
            ->selectRaw('employee_id,
                COUNT(*) as total_records,
                SUM(CASE WHEN status="present" THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status="absent" THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status="late" THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN status="leave" THEN 1 ELSE 0 END) as on_leave,
                SUM(hours_worked) as total_hours,
                SUM(overtime_hours) as overtime_hours')
            ->groupBy('employee_id')
            ->with('employee:id,full_name,employee_number')
            ->get();

        return response()->json(['success' => true, 'data' => $query]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = Attendance::where('tenant_id', $this->tid())->findOrFail($id);
        $record->update($request->validate([
            'check_in'    => 'nullable|date_format:H:i',
            'check_out'   => 'nullable|date_format:H:i',
            'status'      => 'nullable|in:present,absent,late,half_day,leave,holiday,weekend',
            'notes'       => 'nullable|string|max:300',
        ]));
        return response()->json(['success' => true, 'data' => $record]);
    }

    public function destroy(int $id): JsonResponse
    {
        Attendance::where('tenant_id', $this->tid())->findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Attendance record deleted.']);
    }
}
