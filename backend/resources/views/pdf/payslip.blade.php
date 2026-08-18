@extends('pdf.layout')

@section('content')
{{-- Header --}}
<div class="header">
    <div style="display:flex; align-items:center; gap:12px;">
        @include('pdf.partials.logo')
        <div>
            <div class="company-name">{{ $company['name'] ?? 'Company' }}</div>
            <div class="company-info">
                {{ $company['address'] ?? '' }}<br>
                @if($company['phone'] ?? null)Tel: {{ $company['phone'] }}<br>@endif
                @if($company['email'] ?? null){{ $company['email'] }}@endif
            </div>
        </div>
    </div>
    <div>
        <div class="doc-title">PAYSLIP</div>
        <div class="doc-meta">
            <strong>Period:</strong> {{ $run->period->name ?? 'N/A' }}<br>
            <strong>{{ $run->period->start_date?->format('d M Y') ?? '' }} — {{ $run->period->end_date?->format('d M Y') ?? '' }}</strong>
        </div>
    </div>
</div>

{{-- Employee Info --}}
<table class="info-table">
    <tr>
        <td style="width:50%">
            <strong>Employee Name:</strong><br>
            {{ $run->employee->full_name ?? '—' }}<br><br>
            <strong>Employee No:</strong><br>
            {{ $run->employee->employee_number ?? '—' }}<br><br>
            <strong>Department:</strong><br>
            {{ $run->employee->department->name ?? '—' }}
        </td>
        <td style="width:50%">
            <strong>Position:</strong><br>
            {{ $run->employee->position->title ?? '—' }}<br><br>
            <strong>Days Worked:</strong><br>
            {{ $run->days_worked }} / {{ $run->working_days }}<br><br>
            <strong>Contract Type:</strong><br>
            {{ ucfirst(str_replace('_',' ', $run->employee->contract_type ?? 'full_time')) }}
        </td>
    </tr>
</table>

{{-- Earnings --}}
<h3 class="section-title">Earnings</h3>
<table class="items-table">
    <thead>
        <tr>
            <th class="text-left">Description</th>
            <th class="text-right">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach($run->earnings as $line)
        <tr>
            <td>{{ $line->component_name }}</td>
            <td class="text-right">{{ number_format($line->amount, 2) }}</td>
        </tr>
        @endforeach
        <tr class="total-row">
            <td><strong>Gross Earnings</strong></td>
            <td class="text-right"><strong>{{ number_format($run->gross_earnings, 2) }}</strong></td>
        </tr>
    </tbody>
</table>

{{-- Deductions --}}
<h3 class="section-title">Deductions</h3>
<table class="items-table">
    <thead>
        <tr>
            <th class="text-left">Description</th>
            <th class="text-right">Amount</th>
        </tr>
    </thead>
    <tbody>
        @forelse($run->deductions as $line)
        <tr>
            <td>{{ $line->component_name }}</td>
            <td class="text-right">{{ number_format($line->amount, 2) }}</td>
        </tr>
        @empty
        <tr><td colspan="2" style="text-align:center; color:#9ca3af;">No deductions</td></tr>
        @endforelse
        @if($run->loan_deductions > 0)
        <tr>
            <td>Loan Repayment</td>
            <td class="text-right">{{ number_format($run->loan_deductions, 2) }}</td>
        </tr>
        @endif
        <tr>
            <td>Income Tax</td>
            <td class="text-right">{{ number_format($run->tax_amount, 2) }}</td>
        </tr>
        <tr class="total-row">
            <td><strong>Total Deductions</strong></td>
            <td class="text-right"><strong>{{ number_format($run->total_deductions + $run->tax_amount, 2) }}</strong></td>
        </tr>
    </tbody>
</table>

{{-- Net Pay Summary --}}
<div class="totals-box">
    <table style="width:100%">
        <tr>
            <td>Gross Earnings</td>
            <td class="text-right">{{ number_format($run->gross_earnings, 2) }}</td>
        </tr>
        <tr>
            <td>Total Deductions</td>
            <td class="text-right">({{ number_format($run->total_deductions + $run->tax_amount, 2) }})</td>
        </tr>
        <tr class="grand-total">
            <td><strong>NET PAY</strong></td>
            <td class="text-right"><strong>{{ number_format($run->net_pay, 2) }}</strong></td>
        </tr>
    </table>
</div>

{{-- Attendance Summary --}}
<div style="margin-top:16px; font-size:9px; color:#6b7280; border-top:1px solid #e5e7eb; padding-top:10px;">
    <strong>Attendance:</strong>
    Worked {{ $run->days_worked }} days | Absent {{ $run->days_absent }} days
    @if($run->overtime_hours > 0) | Overtime {{ $run->overtime_hours }} hrs @endif
    @if($run->leave_days_paid > 0) | Paid Leave {{ $run->leave_days_paid }} days @endif
</div>

{{-- Signature --}}
<div style="margin-top:30px; display:flex; justify-content:space-between; font-size:9px;">
    <div style="text-align:center; width:40%">
        <div style="border-top:1px solid #374151; padding-top:6px;">Employee Signature</div>
    </div>
    <div style="text-align:center; width:40%">
        <div style="border-top:1px solid #374151; padding-top:6px;">Authorized Signature</div>
    </div>
</div>

<p style="font-size:8px; color:#9ca3af; text-align:center; margin-top:20px;">
    This payslip is system-generated. Generated on {{ now()->format('d M Y H:i') }}.
</p>

@endsection
