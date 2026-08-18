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
            @if($company['phone'] ?? null) Tel: {{ $company['phone'] }}<br>@endif
            @if($company['email'] ?? null) {{ $company['email'] }}<br>@endif
            @if($company['tax_number'] ?? null) Tax No: {{ $company['tax_number'] }}@endif
        </div>
        </div>
    </div>
    <div>
        <div class="doc-title">INVOICE</div>
        <div class="doc-meta">
            <strong>Invoice #:</strong> {{ $sale->invoice_number ?? 'INV-'.$sale->id }}<br>
            <strong>Date:</strong> {{ \Carbon\Carbon::parse($sale->sale_date ?? $sale->created_at)->format('d M Y') }}<br>
            @if($sale->due_date)
            <strong>Due:</strong> {{ \Carbon\Carbon::parse($sale->due_date)->format('d M Y') }}<br>
            @endif
            @php
                $paid = $sale->payments->sum('amount');
                $due  = ($sale->total ?? 0) - $paid;
                $status = $due <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
            @endphp
            <span class="badge badge-{{ $status }}">{{ strtoupper($status) }}</span>
        </div>
    </div>
</div>

{{-- Parties --}}
<div class="parties">
    <div class="party-box">
        <div class="party-label">Bill To</div>
        <div class="party-name">{{ $sale->customer->name ?? 'Walk-in Customer' }}</div>
        <div class="party-detail">
            @if($sale->customer->phone ?? null)Tel: {{ $sale->customer->phone }}<br>@endif
            @if($sale->customer->email ?? null){{ $sale->customer->email }}<br>@endif
            @if($sale->customer->address ?? null){{ $sale->customer->address }}@endif
        </div>
    </div>
    <div class="party-box" style="text-align:right">
        <div class="party-label">Served By</div>
        <div class="party-name">{{ $sale->cashier->name ?? '—' }}</div>
        @if($sale->branch ?? null)
        <div class="party-detail">Branch: {{ $sale->branch->name ?? '' }}</div>
        @endif
    </div>
</div>

{{-- Items Table --}}
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Description</th>
            <th>Unit</th>
            <th style="text-align:right">Qty</th>
            <th style="text-align:right">Unit Price</th>
            <th style="text-align:right">Discount</th>
            <th style="text-align:right">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($sale->items as $i => $item)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $item->product->name ?? '—' }}@if($item->notes) <br><small style="color:#888">{{ $item->notes }}</small>@endif</td>
            <td>{{ $item->unit->name ?? '' }}</td>
            <td style="text-align:right">{{ number_format($item->quantity, 2) }}</td>
            <td style="text-align:right">{{ number_format($item->unit_price, 2) }}</td>
            <td style="text-align:right">{{ number_format($item->discount ?? 0, 2) }}</td>
            <td style="text-align:right">{{ number_format($item->total, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="clearfix">
    <div style="float:left; width:55%">
        @if($sale->notes)
        <div class="notes"><strong>Notes:</strong> {{ $sale->notes }}</div>
        @endif
    </div>
    <table class="totals">
        <tr><td class="label">Subtotal</td><td class="value">{{ number_format($sale->subtotal ?? 0, 2) }}</td></tr>
        @if(($sale->discount_amount ?? 0) > 0)
        <tr><td class="label">Discount</td><td class="value">&minus; {{ number_format($sale->discount_amount, 2) }}</td></tr>
        @endif
        @if(($sale->tax_amount ?? 0) > 0)
        <tr><td class="label">Tax</td><td class="value">{{ number_format($sale->tax_amount, 2) }}</td></tr>
        @endif
        <tr class="grand"><td><strong>TOTAL</strong></td><td class="value">{{ number_format($sale->total ?? 0, 2) }}</td></tr>
        <tr><td class="label">Amount Paid</td><td class="value" style="color:#059669">&minus; {{ number_format($paid, 2) }}</td></tr>
        <tr><td class="label"><strong>Balance Due</strong></td><td class="value" style="color:{{ $due > 0 ? '#dc2626' : '#059669' }}"><strong>{{ number_format(max($due,0), 2) }}</strong></td></tr>
    </table>
</div>

{{-- Payments History --}}
@if($sale->payments->count() > 0)
<div class="payments-section" style="margin-top:30px">
    <h4>Payment History</h4>
    <table>
        <thead>
            <tr>
                <th>Date</th><th>Method</th><th>Reference</th><th style="text-align:right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->payments as $pay)
            <tr>
                <td>{{ \Carbon\Carbon::parse($pay->payment_date ?? $pay->created_at)->format('d M Y') }}</td>
                <td>{{ $pay->paymentMethod->name ?? '—' }}</td>
                <td>{{ $pay->reference ?? 'RCT-'.$pay->id }}</td>
                <td style="text-align:right">{{ number_format($pay->amount, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
