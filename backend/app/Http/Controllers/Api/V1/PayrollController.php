<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Services\PayrollService;
use App\Services\JournalAutoPostService;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\TenantSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PayrollController extends Controller
{
    public function __construct(private PayrollService $payroll) {}

    private function tid(): int { return auth()->user()->tenant_id; }

    private function theme(Request $request): string
    {
        return in_array($request->get('template'), ['classic', 'modern', 'minimal'])
            ? $request->get('template') : 'classic';
    }

    private function company(): array
    {
        $settings = TenantSetting::where('tenant_id', $this->tid())->first();
        $logoPath  = $settings->logo_path ?? null;
        $logoBase64 = null;
        if ($logoPath) {
            $absPath = str_starts_with($logoPath, '/') ? $logoPath : storage_path('app/public/' . ltrim($logoPath, '/'));
            if (file_exists($absPath)) {
                $mime = mime_content_type($absPath);
                $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($absPath));
            }
        }
        return [
            'name'        => $settings->company_name ?? config('app.name'),
            'address'     => $settings->address       ?? '',
            'phone'       => $settings->phone         ?? '',
            'email'       => $settings->email         ?? '',
            'tax_number'  => $settings->tax_number    ?? '',
            'logo_base64' => $logoBase64,
        ];
    }

    // ── Periods ────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $periods = PayrollPeriod::where('tenant_id', $this->tid())
            ->withCount('runs')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->orderByDesc('start_date')
            ->paginate($request->integer('per_page', 20));
        return response()->json(['success' => true, 'data' => $periods]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:150',
            'period_type' => 'required|in:monthly,biweekly,weekly',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after:start_date',
            'notes'       => 'nullable|string|max:500',
        ]);
        $period = PayrollPeriod::create(array_merge($data, ['tenant_id' => $this->tid(), 'status' => 'draft']));
        return response()->json(['success' => true, 'data' => $period], 201);
    }

    public function show(int $id): JsonResponse
    {
        $period = PayrollPeriod::where('tenant_id', $this->tid())
            ->with(['runs.employee:id,full_name,employee_number,department_id', 'runs.lines'])
            ->findOrFail($id);
        return response()->json(['success' => true, 'data' => $period]);
    }

    public function summary(int $id): JsonResponse
    {
        $period = PayrollPeriod::where('tenant_id', $this->tid())->withSum('runs','gross_earnings')
            ->withSum('runs','net_pay')->withSum('runs','tax_amount')->findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => [
                'period'         => $period,
                'total_employees'=> $period->runs()->count(),
                'total_gross'    => $period->runs_sum_gross_earnings ?? 0,
                'total_net'      => $period->runs_sum_net_pay       ?? 0,
                'total_tax'      => $period->runs_sum_tax_amount    ?? 0,
            ],
        ]);
    }

    // ── Actions ────────────────────────────────────────────────────────

    public function runPayroll(int $id): JsonResponse
    {
        $period = PayrollPeriod::where('tenant_id', $this->tid())->findOrFail($id);
        $period = $this->payroll->calculatePayroll($period);
        return response()->json([
            'success' => true,
            'message' => 'Payroll calculated successfully.',
            'data'    => ['employee_count' => $period->runs()->count()],
        ]);
    }

    public function approve(int $id): JsonResponse
    {
        $period = PayrollPeriod::where('tenant_id', $this->tid())->findOrFail($id);
        $period = $this->payroll->approvePayroll($period, auth()->id());
        return response()->json(['success' => true, 'message' => 'Payroll approved.', 'data' => $period]);
    }

    public function postAccounting(int $id): JsonResponse
    {
        $period = PayrollPeriod::where('tenant_id', $this->tid())->findOrFail($id);
        $period = $this->payroll->postAccounting($period);
        return response()->json([
            'success' => true,
            'message' => 'Payroll posted to accounting.',
            'data'    => [
                'period_status'    => $period->status,
                'journal_entry_id' => $period->journal_entry_id,
            ],
        ]);
    }

    // ── Payslip PDF ────────────────────────────────────────────────────

    public function payslip(Request $request, int $runId): Response
    {
        $run = PayrollRun::where('tenant_id', $this->tid())
            ->with(['employee.department', 'employee.position', 'lines', 'period'])
            ->findOrFail($runId);

        $pdf = Pdf::loadView('pdf.payslip', [
            'run'     => $run,
            'company' => $this->company(),
            'theme'   => $this->theme($request),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('payslip-' . ($run->employee->employee_number ?? $run->employee_id) . '-' . $run->period->name . '.pdf');
    }
}
