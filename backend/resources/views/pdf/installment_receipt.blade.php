@extends('pdf.layout')

@section('content')
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
        <div class="doc-title">INSTALLMENT RECEIPT</div>
        <div class="doc-meta">
            <strong>Receipt #:</strong> INST-{{ str_pad($installment->id, 6, '0', STR_PAD_LEFT) }}<br>
            <strong>Date:</strong> {{ \Carbon\Carbon::parse($installment->paid_at ?? now())->format('d M Y') }}<br>
            <span class="badge {{ $installment->status === 'paid' ? 'badge-paid' : 'badge-partial' }}">
                {{ strtoupper($installment->status) }}
            </span>
        </div>
    </div>
</div>

<div class="parties">
    <div class="party-box">
        <div class="party-label">Customer</div>
        <div class="party-name">{{ $installment->creditSale->customer->name ?? '—' }}</div>
        <div class="party-detail">
            @if($installment->creditSale->customer->phone ?? null)
                Tel: {{ $installment->creditSale->customer->phone }}<br>
            @endif
            @if($installment->creditSale->customer->email ?? null)
                {{ $installment->creditSale->customer->email }}
            @endif
        </div>
    </div>
    <div class="party-box" style="text-align:right">
        <div style="font-size:28px; font-weight:bold; color:#059669; margin-top:8px">
            {{ number_format($installment->paid_amount ?? $installment->due_amount, 2) }}
        </div>
        <div style="font-size:10px; color:#555; margin-top:4px">
            Payment Method: {{ $installment->paymentMethod->name ?? '—' }}
        </div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>Credit Sale Ref</th><th>Installment #</th><th>Due Date</th>
            <th style="text-align:right">Amount Due</th>
            <th style="text-align:right">Amount Paid</th>
            @if(($installment->penalty_amount ?? 0) > 0)<th style="text-align:right">Penalty</th>@endif
            <th style="text-align:right">Remaining</th>
        </tr>
    </thead>
    <tbody>
        @php $remaining = ($installment->due_amount + ($installment->penalty_amount ?? 0)) - ($installment->paid_amount ?? $installment->due_amount); @endphp
        <tr>
            <td>{{ $installment->creditSale->invoice_number ?? 'CS-'.$installment->credit_sale_id }}</td>
            <td style="text-align:center">{{ $installment->installment_number ?? '—' }}</td>
            <td>{{ \Carbon\Carbon::parse($installment->due_date)->format('d M Y') }}</td>
            <td style="text-align:right">{{ number_format($installment->due_amount, 2) }}</td>
            <td style="text-align:right; color:#059669; font-weight:bold">{{ number_format($installment->paid_amount ?? $installment->due_amount, 2) }}</td>
            @if(($installment->penalty_amount ?? 0) > 0)
            <td style="text-align:right; color:#dc2626">{{ number_format($installment->penalty_amount, 2) }}</td>
            @endif
            <td style="text-align:right; color:{{ $remaining > 0 ? '#dc2626' : '#059669' }}">
                <strong>{{ number_format(max($remaining,0), 2) }}</strong>
            </td>
        </tr>
    </tbody>
</table>

{{-- Loan summary --}}
@php
    $allInstallments = $installment->creditSale->installments ?? collect();
    $totalDue   = $allInstallments->sum('due_amount');
    $totalPaid  = $allInstallments->where('status','paid')->sum('due_amount');
    $outstanding= $totalDue - $totalPaid;
@endphp
<div style="margin-top:16px; background:#f8fafc; border:1px solid #e5e7eb; padding:10px; border-radius:4px;">
    <strong style="font-size:11px; color:#555">Loan Summary</strong>
    <table style="margin-top:6px">
        <tr>
            <td style="width:30%; font-size:10px; color:#888">Total Loan</td>
            <td style="font-size:11px; font-weight:bold">{{ number_format($totalDue, 2) }}</td>
            <td style="width:30%; font-size:10px; color:#888">Total Paid</td>
            <td style="font-size:11px; font-weight:bold; color:#059669">{{ number_format($totalPaid, 2) }}</td>
            <td style="width:30%; font-size:10px; color:#888">Outstanding</td>
            <td style="font-size:11px; font-weight:bold; color:{{ $outstanding > 0 ? '#dc2626' : '#059669' }}">{{ number_format($outstanding, 2) }}</td>
        </tr>
    </table>
</div>

<div style="margin-top:40px; text-align:center; font-size:10px; color:#888">
    This is a computer-generated receipt and does not require a physical signature.
</div>
@endsection
