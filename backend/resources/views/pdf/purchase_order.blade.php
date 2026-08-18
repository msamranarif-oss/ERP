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
                @if($company['email'] ?? null){{ $company['email'] }}<br>@endif
                @if($company['tax_number'] ?? null)Tax No: {{ $company['tax_number'] }}@endif
            </div>
        </div>
    </div>
    <div>
        <div class="doc-title">PURCHASE ORDER</div>
        <div class="doc-meta">
            <strong>PO #:</strong> {{ $po->po_number ?? 'PO-'.$po->id }}<br>
            <strong>Date:</strong> {{ \Carbon\Carbon::parse($po->order_date ?? $po->created_at)->format('d M Y') }}<br>
            @if($po->expected_date)
            <strong>Expected:</strong> {{ \Carbon\Carbon::parse($po->expected_date)->format('d M Y') }}<br>
            @endif
            <span class="badge @if($po->status === 'approved') badge-paid @elseif($po->status === 'draft') badge-unpaid @else badge-partial @endif">
                {{ strtoupper($po->status) }}
            </span>
        </div>
    </div>
</div>

<div class="parties">
    <div class="party-box">
        <div class="party-label">Supplier</div>
        <div class="party-name">{{ $po->supplier->name ?? '—' }}</div>
        <div class="party-detail">
            @if($po->supplier->phone ?? null)Tel: {{ $po->supplier->phone }}<br>@endif
            @if($po->supplier->email ?? null){{ $po->supplier->email }}<br>@endif
            @if($po->supplier->address ?? null){{ $po->supplier->address }}@endif
        </div>
    </div>
    <div class="party-box" style="text-align:right">
        <div class="party-label">Deliver To</div>
        <div class="party-name">{{ $po->warehouse->name ?? ($company['name'] ?? '') }}</div>
        <div class="party-detail">
            {{ $company['address'] ?? '' }}<br>
            @if($po->createdBy ?? null)Ordered by: {{ $po->createdBy->name }}@endif
        </div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>#</th><th>Product</th><th>SKU</th>
            <th style="text-align:right">Ordered Qty</th>
            <th style="text-align:right">Received Qty</th>
            <th style="text-align:right">Unit Price</th>
            <th style="text-align:right">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($po->items as $i => $item)
        <tr>
            <td>{{ $i+1 }}</td>
            <td>{{ $item->product->name ?? '—' }}</td>
            <td>{{ $item->product->sku ?? '' }}</td>
            <td style="text-align:right">{{ number_format($item->quantity, 2) }}</td>
            <td style="text-align:right">{{ number_format($item->received_quantity_total, 2) }}</td>
            <td style="text-align:right">{{ number_format($item->unit_price, 2) }}</td>
            <td style="text-align:right">{{ number_format($item->total, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="clearfix">
    <div style="float:left;width:52%">
        @if($po->notes)
        <div class="notes"><strong>Notes:</strong> {{ $po->notes }}</div>
        @endif
        @if($po->terms)
        <div class="notes" style="margin-top:6px"><strong>Terms:</strong> {{ $po->terms }}</div>
        @endif
    </div>
    <table class="totals">
        @php $subtotal = $po->subtotal; @endphp
        <tr><td class="label">Subtotal</td><td class="value">{{ number_format($subtotal, 2) }}</td></tr>
        @if(($po->tax_amount ?? 0) > 0)
        <tr><td class="label">Tax</td><td class="value">{{ number_format($po->tax_amount, 2) }}</td></tr>
        @endif
        <tr class="grand"><td><strong>ORDER TOTAL</strong></td><td class="value">{{ number_format($po->total ?? $subtotal, 2) }}</td></tr>
    </table>
</div>

<div style="margin-top:50px; display:flex; justify-content:space-between; font-size:10px; color:#888">
    <div style="text-align:center; width:40%">
        <div style="border-top:1px solid #ccc; padding-top:4px">Prepared By</div>
    </div>
    <div style="text-align:center; width:40%">
        <div style="border-top:1px solid #ccc; padding-top:4px">Authorized Signature</div>
    </div>
</div>
@endsection
