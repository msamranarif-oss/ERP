<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Models\EmployeeLoan;
use App\Models\Attendance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollService
{
    public function __construct(
        private JournalAutoPostService $journal
    ) {}

    /**
     * Calculate payroll for all active employees in a period.
     * Deletes any existing draft runs first, then recalculates.
     */
    public function calculatePayroll(PayrollPeriod $period): PayrollPeriod
    {
        if (!in_array($period->status, ['draft', 'processing'])) {
            throw new \RuntimeException("Cannot recalculate a payload with status: {$period->status}");
        }

        $period->update(['status' => 'processing']);

        // Remove previous draft runs
        PayrollRun::where('payroll_period_id', $period->id)->where('status', 'draft')->delete();

        $employees = Employee::where('tenant_id', $period->tenant_id)
            ->where('status', 'active')
            ->with(['currentSalary.components.component', 'loans' => fn($q) => $q->where('status', 'active')])
            ->get();

        $workingDays = $this->countWorkingDays($period->start_date, $period->end_date);

        DB::transaction(function () use ($period, $employees, $workingDays) {
            foreach ($employees as $employee) {
                $this->calculateEmployeePayroll($period, $employee, $workingDays);
            }
        });

        $period->update(['status' => 'draft']);
        return $period->fresh(['runs']);
    }

    /**
     * Approve a payroll period — locks all runs.
     */
    public function approvePayroll(PayrollPeriod $period, int $approvedBy): PayrollPeriod
    {
        if ($period->status !== 'draft') {
            throw new \RuntimeException("Only draft payroll periods can be approved.");
        }

        DB::transaction(function () use ($period, $approvedBy) {
            $period->runs()->update(['status' => 'approved']);
            $period->update([
                'status'      => 'approved',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
            ]);
        });

        return $period->fresh();
    }

    /**
     * Post payroll accounting entries via JournalAutoPostService.
     */
    public function postAccounting(PayrollPeriod $period): PayrollPeriod
    {
        if ($period->status !== 'approved') {
            throw new \RuntimeException("Only approved payroll periods can be posted to accounting.");
        }

        $entry = $this->journal->postPayroll($period);

        if ($entry) {
            $period->update([
                'journal_entry_id' => $entry->id,
                'status'           => 'paid',
            ]);
            $period->runs()->update(['status' => 'paid']);

            // Reduce outstanding loan balances
            $this->applyLoanDeductions($period);
        }

        return $period->fresh();
    }

    // ─────────────────────────────────────────────────────────────────
    //  Private helpers
    // ─────────────────────────────────────────────────────────────────

    private function calculateEmployeePayroll(PayrollPeriod $period, Employee $employee, int $workingDays): PayrollRun
    {
        $salary = $employee->currentSalary;
        $basicSalary = $salary ? (float)$salary->basic_salary : 0;

        // Attendance summary for this period
        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$period->start_date, $period->end_date])
            ->selectRaw("
                SUM(CASE WHEN status IN ('present','late','half_day') THEN 1 ELSE 0 END) as days_worked,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as days_absent,
                SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave_days,
                SUM(overtime_hours) as overtime_hours
            ")->first();

        $daysWorked   = (int)($attendance->days_worked ?? $workingDays);
        $daysAbsent   = (int)($attendance->days_absent ?? 0);
        $leaveDays    = (float)($attendance->leave_days ?? 0);
        $overtimeHrs  = (float)($attendance->overtime_hours ?? 0);

        // Pro-rate basic salary by attendance if absences exist
        $dailyRate   = $workingDays > 0 ? $basicSalary / $workingDays : $basicSalary;
        $absentDeduct = $dailyRate * $daysAbsent;
        $proRatedBasic = $basicSalary - $absentDeduct;

        $earningsLines    = [];
        $deductionLines   = [];
        $taxLines         = [];
        $grossEarnings    = $proRatedBasic;
        $totalDeductions  = 0;
        $taxAmount        = 0;

        // Add basic salary line
        $earningsLines[] = ['component_name' => 'Basic Salary', 'type' => 'earning', 'amount' => $proRatedBasic, 'salary_component_id' => null];

        // Process salary components
        if ($salary) {
            foreach ($salary->components as $ec) {
                $component = $ec->component;
                if (!$component || !$component->is_active) continue;

                $amount = $this->resolveComponentAmount($ec->value, $component, $basicSalary, $grossEarnings);

                if ($amount == 0) continue;

                $line = [
                    'salary_component_id' => $component->id,
                    'component_name'      => $component->name,
                    'type'                => $component->type,
                    'amount'              => round($amount, 2),
                ];

                if ($component->type === 'earning') {
                    $grossEarnings += $amount;
                    $earningsLines[] = $line;
                } elseif ($component->type === 'deduction') {
                    $totalDeductions += $amount;
                    $deductionLines[] = $line;
                } elseif ($component->type === 'tax') {
                    $taxAmount += $amount;
                    $taxLines[] = $line;
                }
            }
        }

        // Overtime — simple 1.5x hourly calc
        if ($overtimeHrs > 0 && $workingDays > 0) {
            $hourlyRate  = $proRatedBasic / ($workingDays * 8);
            $overtimePay = round($hourlyRate * 1.5 * $overtimeHrs, 2);
            $grossEarnings += $overtimePay;
            $earningsLines[] = ['component_name' => 'Overtime', 'type' => 'earning', 'amount' => $overtimePay, 'salary_component_id' => null];
        }

        // Active loan deductions
        $loanDeductions = 0;
        foreach ($employee->loans as $loan) {
            $deductAmt = min((float)$loan->monthly_deduction, (float)$loan->balance);
            if ($deductAmt > 0) {
                $loanDeductions += $deductAmt;
                $deductionLines[] = ['component_name' => 'Loan Deduction', 'type' => 'deduction', 'amount' => $deductAmt, 'salary_component_id' => null];
            }
        }

        $netPay = $grossEarnings - $totalDeductions - $loanDeductions - $taxAmount;

        // Create the payroll run
        $run = PayrollRun::create([
            'tenant_id'         => $period->tenant_id,
            'payroll_period_id' => $period->id,
            'employee_id'       => $employee->id,
            'working_days'      => $workingDays,
            'days_worked'       => $daysWorked,
            'days_absent'       => $daysAbsent,
            'leave_days_paid'   => $leaveDays,
            'leave_days_unpaid' => 0,
            'overtime_hours'    => $overtimeHrs,
            'basic_salary'      => $basicSalary,
            'gross_earnings'    => round($grossEarnings, 2),
            'total_deductions'  => round($totalDeductions + $loanDeductions, 2),
            'loan_deductions'   => round($loanDeductions, 2),
            'tax_amount'        => round($taxAmount, 2),
            'net_pay'           => round(max($netPay, 0), 2),
            'status'            => 'draft',
        ]);

        // Persist all lines
        foreach (array_merge($earningsLines, $deductionLines, $taxLines) as $line) {
            PayrollRunLine::create(array_merge($line, ['payroll_run_id' => $run->id]));
        }

        return $run;
    }

    private function resolveComponentAmount(float $overrideValue, $component, float $basic, float $gross): float
    {
        $base = $overrideValue > 0 ? $overrideValue : (float)$component->default_value;

        return match($component->calculation_type) {
            'percentage_of_basic' => $basic > 0 ? round($basic * ($base / 100), 2) : 0,
            'percentage_of_gross' => $gross > 0 ? round($gross * ($base / 100), 2) : 0,
            default               => $base, // fixed
        };
    }

    private function applyLoanDeductions(PayrollPeriod $period): void
    {
        $runs = PayrollRun::where('payroll_period_id', $period->id)->get();
        foreach ($runs as $run) {
            if ($run->loan_deductions <= 0) continue;
            $remaining = $run->loan_deductions;
            $loans = EmployeeLoan::where('employee_id', $run->employee_id)->where('status', 'active')->orderBy('start_date')->get();
            foreach ($loans as $loan) {
                $deduct = min((float)$loan->monthly_deduction, (float)$loan->balance, $remaining);
                $loan->balance -= $deduct;
                $remaining     -= $deduct;
                if ($loan->balance <= 0) $loan->status = 'completed';
                $loan->save();
                if ($remaining <= 0) break;
            }
        }
    }

    private function countWorkingDays(Carbon|string $start, Carbon|string $end): int
    {
        $start = Carbon::parse($start);
        $end   = Carbon::parse($end);
        $days  = 0;
        $current = $start->copy();
        while ($current->lte($end)) {
            if (!$current->isWeekend()) $days++;
            $current->addDay();
        }
        return max($days, 1);
    }
}
