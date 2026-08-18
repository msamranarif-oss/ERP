@extends('pdf.layout')

@section('content')
<div class="header">
    <div style="display:flex; align-items:center; gap:12px;">
        @include('pdf.partials.logo')
        <div>
            <div class="company-name">{{ $company['name'] ?? 'Company' }}</div>
            <div class="company-info">
                {{ $company['address'] ?? '' }}<br>
                @if($company['phone'] ?? null) Tel: {{ $company['phone'] }}<br>@endif
                @if($company['email'] ?? null) {{ $company['email'] }}@endif
            </div>
        </div>
    </div>
    <div>
        <div class="doc-title">PAYMENT RECEIPT</div>
        <div class="doc-meta">
            <strong>Receipt #:</strong> RCT-{{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}<br>
            <strong>Date:</strong> {{ \Carbon\Carbon::parse($payment->payment_date ?? $payment->created_at)->format('d M Y') }}
        </div>
    </div>
</div>

<div class="parties">
    <div class="party-box">
        <div class="party-label">Received From</div>
        <div class="party-name">{{ $payment->sale->customer->name ?? 'Walk-in Customer' }}</div>
        <div class="party-detail">
            @if(($payment->sale->customer->phone ?? null)) Tel: {{ $payment->sale->customer->phone }}<br>@endif
            @if(($payment->sale->customer->email ?? null)) {{ $payment->sale->customer->email }}@endif
        </div>
    </div>
    <div class="party-box" style="text-align:right; padding-top:8px">
        <div style="font-size:30px; font-weight:bold; color:#059669">
            {{ number_format($payment->amount, 2) }}
        </div>
        <div style="font-size:10px; color:#555; margin-top:4px">
            Payment Method: {{ $payment->paymentMethod->name ?? '—' }}
        </div>
        @if($payment->reference)
        <div style="font-size:10px; color:#555">Ref: {{ $payment->reference }}</div>
        @endif
    </div>
</div>

<table>
    <thead>
        <tr><th>Invoice #</th><th>Invoice Date</th><th style="text-align:right">Invoice Total</th><th style="text-align:right">This Payment</th><th style="text-align:right">Balance Due</th></tr>
    </thead>
    <tbody>
        @php
            $invoiceTotal = $payment->sale->total ?? 0;
            $totalPaid    = $payment->sale->payments->sum('amount');
            $balance      = $invoiceTotal - $totalPaid;
        @endphp
        <tr>
            <td>{{ $payment->sale->invoice_number ?? 'INV-'.$payment->sale->id }}</td>
            <td>{{ \Carbon\Carbon::parse($payment->sale->sale_date ?? $payment->sale->created_at)->format('d M Y') }}</td>
            <td style="text-align:right">{{ number_format($invoiceTotal, 2) }}</td>
            <td style="text-align:right; color:#059669; font-weight:bold">{{ number_format($payment->amount, 2) }}</td>
            <td style="text-align:right; color:{{ $balance > 0 ? '#dc2626' : '#059669' }}; font-weight:bold">{{ number_format(max($balance,0), 2) }}</td>
        </tr>
    </tbody>
</table>

@if($payment->notes)
<div class="notes">{{ $payment->notes }}</div>
@endif

<div style="margin-top:40px; text-align:center; font-size:10px; color:#888">
    This is a computer-generated receipt and does not require a physical signature.
</div>
@endsection
