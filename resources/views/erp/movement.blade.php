@extends('erp.layout')
@use('App\Support\Fmt')
@use('App\Services\StockTrace')
@php($m = $row->m)
@section('title', 'Stock entry #'.$m->id)
@section('crumb')<a href="{{ route('erp.ledger') }}">Stock Ledger</a>@endsection
@section('subtitle')
  <span class="badge-s {{ $row->statusTone }}"><span class="dot"></span>{{ $row->statusLabel }}</span> <span class="ms-2">{{ $row->typeLabel }} · {{ $m->product->name }}</span>
@endsection
@section('actions')
  @if($distributorId)<a href="{{ route('erp.distributors.show', [$distributorId, 'tab' => 'movements', 'product_id' => $m->product_id]) }}" class="btn btn-light"><x-icon name="building" size="sm"/>Distributor history</a>@endif
  <a href="{{ route('erp.ledger', ['product_id' => $m->product_id]) }}" class="btn btn-light"><x-icon name="ledger" size="sm"/>All entries for product</a>
@endsection
@section('content')
<div class="row g-3">
  <div class="col-xl-5">
    <section class="panel mb-3"><div class="panel-head"><div><h2>Transaction</h2></div></div>
      <div class="panel-body"><dl class="kv">
        <dt>Date and time</dt><dd>{{ $m->created_at->format('d M Y, H:i') }}</dd>
        <dt>Product</dt><dd>{{ $m->product->name }} <span class="mono text-muted">{{ $m->product->sku }}</span></dd>
        <dt>Quantity</dt><dd class="num">{{ Fmt::num($m->qty) }} {{ $m->product->unit }}</dd>
        <dt>Movement type</dt><dd>{{ $row->kindLabel }} · {{ $row->typeLabel }}</dd>
        <dt>Source</dt><dd>{{ $row->source }}</dd>
        <dt>Destination</dt><dd>{{ $row->destination }}</dd>
        <dt>Current location</dt><dd>{{ $row->currentLocation }}</dd>
        <dt>Reference</dt><dd class="mono">{{ $row->reference }}</dd>
        @if($row->transfer)<dt>Challan</dt><dd><a class="mono" href="{{ route('erp.transfers') }}">{{ $row->transfer->number }}</a></dd>@endif
        @if($row->order)<dt>Order</dt><dd><a class="mono" href="{{ route('erp.orders', ['q' => $row->order->number]) }}">{{ $row->order->number }}</a></dd>@endif
        @if($row->invoice)<dt>Invoice</dt><dd><a class="mono" href="{{ route('erp.invoice', $row->invoice) }}">{{ $row->invoice->number }}</a></dd>@endif
        <dt>Dispatch date</dt><dd>{{ $row->dispatchedAt?->format('d M Y, H:i') ?? '—' }}</dd>
        <dt>Delivery date</dt><dd>{{ $row->deliveredAt?->format('d M Y, H:i') ?? '—' }}</dd>
        <dt>Handled by</dt><dd>{{ $m->user?->name ?? 'System' }}</dd>
        @if($row->transfer)<dt>Raised / approved</dt><dd>{{ $people[$row->transfer->created_by] ?? '—' }} / {{ $people[$row->transfer->approved_by] ?? '—' }}</dd>@endif
        <dt>Reason</dt><dd>{{ $m->reason ?: '—' }}</dd>
        @foreach($balances as $b)<dt>Left at {{ $b['name'] }}</dt><dd class="num">{{ Fmt::num($b['qty']) }} after this entry</dd>@endforeach
      </dl></div>
    </section>
  </div>
  <div class="col-xl-7">
    <section class="panel mb-3"><div class="panel-head"><div><h2>Trace of this document</h2><p class="hint">Every ledger entry for this product on {{ $row->reference }}, oldest first</p></div></div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Date</th><th>Type</th><th>Source</th><th>Destination</th><th class="text-end">Qty</th><th>By</th></tr></thead>
        <tbody>
        @foreach($chain as $c)
          <tr class="{{ $c->m->id === $m->id ? 'table-active' : '' }}">
            <td class="text-nowrap"><a href="{{ route('erp.ledger.show', $c->m) }}">{{ $c->m->created_at->format('d M Y H:i') }}</a></td>
            <td>{{ $c->typeLabel }}</td><td>{{ $c->source }}</td><td>{{ $c->destination }}</td>
            <td class="text-end num">{{ Fmt::num($c->m->qty) }}</td><td>{{ $c->m->user?->name ?? 'System' }}</td>
          </tr>
        @endforeach
        </tbody>
      </table></div>
    </section>
    <section class="panel mb-3"><div class="panel-head"><div><h2>Where {{ $m->product->name }} is now</h2><p class="hint">Live balance at every location in the network</p></div></div>
      <div class="panel-body d-flex flex-wrap gap-2">
        @forelse($network as $b)<span class="tag">{{ $b->location->name }} · {{ Fmt::num($b->qty) }}</span>@empty<span class="text-muted">No stock recorded.</span>@endforelse
      </div>
    </section>
    <section class="panel"><div class="panel-head"><div><h2>Audit trail</h2></div></div>
      @if($logs->count())
      <ol class="timeline">
        @foreach($logs as $l)
          <li><div class="when">{{ $l->created_at->format('d M Y, H:i') }}</div>
            <div class="what"><b>{{ Str::headline(str_replace('.', ' ', $l->action)) }}</b> by {{ $l->user?->name ?? 'System' }}@if($l->ip) · {{ $l->ip }}@endif</div>
            @if($l->data)<div class="cell-sub">{{ StockTrace::auditSummary($l->data) }}</div>@endif</li>
        @endforeach
      </ol>
      @else<x-erp.empty icon="activity" title="No audit entries"/>@endif
    </section>
  </div>
</div>
@endsection
