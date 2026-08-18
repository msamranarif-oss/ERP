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
                @if($company['tax_number'] ?? null)Tax No: {{ $company['tax_number'] }}@endif
            </div>
        </div>
    </div>
    <div>
        <div class="doc-title">PURCHASE BILL</div>
        <div class="doc-meta">
            <strong>Bill #:</strong> {{ $bill->bill_number ?? 'BILL-'.$bill->id }}<br>
            <strong>Bill Date:</strong> {{ \Carbon\Carbon::parse($bill->bill_date ?? $bill->created_at)->format('d M Y') }}<br>
            @if($bill->due_date)
            <strong>Due:</strong> {{ \Carbon\Carbon::parse($bill->due_date)->format('d M Y') }}<br>
            @endif
            @php
                $balance = ($bill->total ?? 0) - ($bill->paid_amount ?? 0);
                $status  = $balance <= 0 ? 'paid' : (($bill->paid_amount ?? 0) > 0 ? 'partial' : 'unpaid');
            @endphp
            <span class="badge badge-{{ $status }}">{{ strtoupper($status) }}</span>
        </div>
    </div>
</div>

<div class="parties">
    <div class="party-box">
        <div class="party-label">Supplier</div>
        <div class="party-name">{{ $bill->supplier->name ?? '—' }}</div>
        <div class="party-detail">
            @if($bill->supplier->phone ?? null)Tel: {{ $bill->supplier->phone }}<br>@endif
            @if($bill->supplier->email ?? null){{ $bill->supplier->email }}<br>@endif
            @if($bill->supplier->address ?? null){{ $bill->supplier->address }}@endif
        </div>
    </div>
    <div class="party-box" style="text-align:right">
        <div class="party-label">Ref PO</div>
        <div class="party-name">{{ $bill->purchaseOrder->po_number ?? ($bill->po_reference ?? '—') }}</div>
        @if($bill->createdBy ?? null)
        <div class="party-detail">Prepared by: {{ $bill->createdBy->name ?? '' }}</div>
        @endif
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>#</th><th>Product</th><th>SKU</th>
            <th style="text-align:right">Received Qty</th>
            <th style="text-align:right">Unit Price</th>
            <th style="text-align:right">Tax</th>
            <th style="text-align:right">Total</th>
        </tr>
    </thead>
    <tbody>
        @php $computedSubtotal = 0; $computedTax = 0; @endphp
        @foreach($bill->items as $i => $item)
        @php
            $receivedQty = $item->purchaseOrderItem
                ? $item->purchaseOrderItem->grnItems->sum('quantity_received')
                : $item->quantity;
            $lineSubtotal = $receivedQty * $item->unit_price;
            $lineTax      = (float)($item->tax ?? 0);
            $lineTotal    = $lineSubtotal + $lineTax;
            $computedSubtotal += $lineSubtotal;
            $computedTax      += $lineTax;
        @endphp
        <tr>
            <td>{{ $i+1 }}</td>
            <td>{{ $item->product->name ?? '—' }}</td>
            <td>{{ $item->product->sku ?? '' }}</td>
            <td style="text-align:right">{{ number_format($receivedQty, 2) }}</td>
            <td style="text-align:right">{{ number_format($item->unit_price, 2) }}</td>
            <td style="text-align:right">{{ number_format($lineTax, 2) }}</td>
            <td style="text-align:right">{{ number_format($lineTotal, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="clearfix">
    <div style="float:left;width:52%">
        @if($bill->notes)
        <div class="notes">{{ $bill->notes }}</div>
        @endif
    </div>
    @php
        $computedTotal   = $computedSubtotal + $computedTax + ($bill->shipping_cost ?? 0);
        $computedBalance = $computedTotal - ($bill->paid_amount ?? 0);
    @endphp
    <table class="totals">
        <tr><td class="label">Subtotal</td><td class="value">{{ number_format($computedSubtotal, 2) }}</td></tr>
        @if($computedTax > 0)
        <tr><td class="label">Tax</td><td class="value">{{ number_format($computedTax, 2) }}</td></tr>
        @endif
        @if(($bill->shipping_cost ?? 0) > 0)
        <tr><td class="label">Shipping</td><td class="value">{{ number_format($bill->shipping_cost, 2) }}</td></tr>
        @endif
        <tr class="grand"><td><strong>TOTAL DUE</strong></td><td class="value">{{ number_format($computedTotal, 2) }}</td></tr>
        <tr><td class="label">Amount Paid</td><td class="value" style="color:#059669">{{ number_format($bill->paid_amount ?? 0, 2) }}</td></tr>
        <tr><td class="label"><strong>Balance</strong></td>
            <td class="value" style="color:{{ $computedBalance > 0 ? '#dc2626' : '#059669' }}">
                <strong>{{ number_format(max($computedBalance, 0), 2) }}</strong>
            </td>
        </tr>
    </table>
</div>
@endsection
