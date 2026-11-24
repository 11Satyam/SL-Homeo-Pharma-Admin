@extends('erp.layout')
@use('App\Support\Fmt')
@section('title', 'Transfer invoice '.$t->invoiceNumber())
@section('crumb')<a href="{{ route('erp.transfers') }}">Transfers</a>@endsection
@php($tot = $t->totals())
@php($dist = $t->to->distributor)
@section('subtitle')
  <x-erp.status :value="$t->status"/> <span class="ms-2">Transfer {{ $t->number }} · {{ $t->from->name }} → {{ $t->to->name }}</span>
@endsection
@section('actions')
  <a href="{{ route('erp.transfers') }}" class="btn btn-light"><x-icon name="truck" size="sm"/>All transfers</a>
  <button type="button" class="btn btn-primary" onclick="window.print()"><x-icon name="printer" size="sm"/>Print / PDF</button>
@endsection
@section('content')
<article class="panel doc" id="print">
  <header class="doc-head">
    <div>
      <div class="d-flex align-items-center gap-2 mb-3"><span class="brand-mark" style="width:30px;height:30px;font-size:.75rem">SL</span><b>SL Distribution</b></div>
      <div class="text-muted small">Dispatched from <b>{{ $t->from->name }}</b></div>
    </div>
    <div class="text-md-end">
      <div class="text-muted small text-uppercase fw-semibold" style="letter-spacing:.08em">Transfer invoice</div>
      <div class="fs-4 fw-bold mono">{{ $t->invoiceNumber() }}</div>
      <dl class="kv mt-2" style="grid-template-columns:auto auto;justify-content:end">
        <dt>Date</dt><dd>{{ $t->created_at->format('d M Y') }}</dd>
        <dt>Transfer</dt><dd class="mono">{{ $t->number }}</dd>
        @if($t->dispatched_at)<dt>Dispatched</dt><dd>{{ $t->dispatched_at->format('d M Y') }}</dd>@endif
        @if($t->remarks)<dt>Remarks</dt><dd>{{ $t->remarks }}</dd>@endif
      </dl>
    </div>
  </header>
  <div class="doc-body">
    <div class="mb-4"><div class="text-muted small text-uppercase fw-semibold mb-1" style="letter-spacing:.06em">Ship to</div>
      <div class="fw-semibold">{{ $dist?->business_name ?? $t->to->name }}</div>
      @if($dist)<div class="text-muted small">{{ collect([$dist->contact_person, $dist->phone, $dist->email])->filter()->implode(' · ') }}@if($dist->address)<br>{{ $dist->address }}@endif</div>
      @else<div class="text-muted small">{{ Fmt::label($t->to->type) }}</div>@endif
    </div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>#</th><th>Product</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Gross</th><th class="text-end">Disc.</th><th class="text-end">Taxable</th><th class="text-end">GST</th><th class="text-end">Amount</th></tr></thead>
      <tbody>@foreach($t->lines as $n => $l)@php($a = $l->totals())
        <tr><td class="text-muted">{{ $n + 1 }}</td><td>{{ $l->product->name }}<span class="cell-sub mono">{{ $l->product->sku }}</span></td>
          <td class="text-end num">{{ $l->qty }} {{ $l->product->unit }}</td><td class="text-end num">{{ Fmt::money($l->unit_price) }}</td><td class="text-end num">{{ Fmt::money($a['gross']) }}</td>
          <td class="text-end num">@if($a['discount'] > 0){{ (float) $l->discount_pct }}%<span class="cell-sub">− {{ Fmt::money($a['discount']) }}</span>@else—@endif</td>
          <td class="text-end num">{{ Fmt::money($a['taxable']) }}</td><td class="text-end num">{{ (float) $l->tax_rate }}%<span class="cell-sub">{{ Fmt::money($a['tax']) }}</span></td>
          <td class="text-end num fw-semibold">{{ Fmt::money($a['total']) }}</td></tr>
      @endforeach</tbody>
    </table></div>
    <div class="totals mt-3 num">
      <div><span class="text-muted">Subtotal ({{ $t->lines->sum('qty') }} units)</span><span>{{ Fmt::money($tot['gross']) }}</span></div>
      <div><span class="text-muted">Discount</span><span>− {{ Fmt::money($tot['discount']) }}</span></div>
      <div><span class="text-muted">Taxable value</span><span>{{ Fmt::money($tot['taxable']) }}</span></div>
      <div><span class="text-muted">GST</span><span>{{ Fmt::money($tot['tax']) }}</span></div>
      <div class="grand"><span>Total</span><span>{{ Fmt::money($tot['total']) }}</span></div>
    </div>
    <p class="text-muted small mt-4 mb-0">Rates and GST are as recorded when the transfer was created. This is a computer-generated transfer invoice.</p>
  </div>
</article>
@endsection
