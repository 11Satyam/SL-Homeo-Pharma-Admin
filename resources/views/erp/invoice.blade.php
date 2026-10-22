@extends('erp.layout')
@use('App\Support\Fmt')
@section('title', 'Invoice '.$invoice->number)
@section('crumb')<a href="{{ route('erp.invoices') }}">Invoices</a>@endsection
@php($out = (float) $invoice->outstanding())
@php($st = $out <= 0.004 ? 'paid' : ($invoice->due_date->lt(today()) ? 'overdue' : ($out < (float) $invoice->total ? 'partial' : 'unpaid')))
@php($lines = $order->lines->where('delivered_qty', '>', 0))
@php($net = $lines->sum(fn ($l) => $l->price * $l->delivered_qty))
@section('subtitle')
  <x-erp.status :value="$st"/> <span class="ms-2">Order {{ $order->number }} · {{ $invoice->shop->name }}</span>
@endsection
@section('actions')
  <a href="{{ route('erp.shop', $invoice->shop) }}" class="btn btn-light"><x-icon name="store" size="sm"/>Shop statement</a>
  <button type="button" class="btn btn-primary" onclick="window.print()"><x-icon name="printer" size="sm"/>Print / PDF</button>
@endsection
@section('content')
<article class="panel doc" id="print">
  <header class="doc-head">
    <div>
      <div class="d-flex align-items-center gap-2 mb-3"><span class="brand-mark" style="width:30px;height:30px;font-size:.75rem">SL</span><b>{{ $order->distributor->business_name }}</b></div>
      <div class="text-muted small">{{ $order->distributor->address }}</div>
      <div class="text-muted small">{{ $order->distributor->phone }} {{ $order->distributor->email }}</div>
    </div>
    <div class="text-md-end">
      <div class="text-muted small text-uppercase fw-semibold" style="letter-spacing:.08em">Tax invoice</div>
      <div class="fs-4 fw-bold mono">{{ $invoice->number }}</div>
      <dl class="kv mt-2" style="grid-template-columns:auto auto;justify-content:end">
        <dt>Issued</dt><dd>{{ $invoice->issue_date->format('d M Y') }}</dd>
        <dt>Due</dt><dd class="{{ $st === 'overdue' ? 'text-danger' : '' }}">{{ $invoice->due_date->format('d M Y') }}</dd>
        <dt>Order</dt><dd class="mono">{{ $order->number }}</dd>
      </dl>
    </div>
  </header>
  <div class="doc-body">
    <div class="mb-4"><div class="text-muted small text-uppercase fw-semibold mb-1" style="letter-spacing:.06em">Bill to</div>
      <div class="fw-semibold">{{ $invoice->shop->name }}</div><div class="text-muted small">{{ $invoice->shop->owner }} · {{ $invoice->shop->phone }}<br>{{ $invoice->shop->address }}</div></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>#</th><th>Product</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">GST</th><th class="text-end">Amount</th></tr></thead>
      <tbody>@foreach($lines->values() as $n => $l)<tr><td class="text-muted">{{ $n + 1 }}</td><td>{{ $l->product->name }}<span class="cell-sub mono">{{ $l->product->sku }}</span></td><td class="text-end num">{{ $l->delivered_qty }} {{ $l->product->unit }}</td><td class="text-end num">{{ Fmt::money($l->price) }}</td><td class="text-end num">{{ (float) $l->tax_rate }}%</td><td class="text-end num">{{ Fmt::money($l->price * $l->delivered_qty * (1 + $l->tax_rate / 100)) }}</td></tr>@endforeach</tbody>
    </table></div>
    <div class="totals mt-3 num">
      <div><span class="text-muted">Taxable value</span><span>{{ Fmt::money($net) }}</span></div>
      <div><span class="text-muted">GST</span><span>{{ Fmt::money($invoice->total - $net) }}</span></div>
      <div class="grand"><span>Invoice total</span><span>{{ Fmt::money($invoice->total) }}</span></div>
      <div><span class="text-muted">Payments allocated</span><span>− {{ Fmt::money($invoice->allocations->sum('amount')) }}</span></div>
      <div><span class="text-muted">Credit notes</span><span>− {{ Fmt::money($invoice->creditNotes->sum('amount')) }}</span></div>
      <div class="grand {{ $st === 'overdue' ? 'text-danger' : '' }}"><span>Balance due</span><span>{{ Fmt::money(max(0, $out)) }}</span></div>
    </div>
    @if($invoice->creditNotes->count())
      <div class="mt-4"><div class="form-section">Credit notes</div>
        @foreach($invoice->creditNotes as $cn)<div class="d-flex justify-content-between small py-1 border-bottom"><span>{{ $cn->created_at->format('d M Y') }} · {{ $cn->reason }}</span><span class="num">{{ Fmt::money($cn->amount) }}</span></div>@endforeach</div>
    @endif
    <p class="text-muted small mt-4 mb-0">Payment terms: {{ $invoice->shop->payment_terms_days }} days. This is a computer-generated invoice.</p>
  </div>
</article>
@endsection
