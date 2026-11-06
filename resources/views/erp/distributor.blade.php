@extends('erp.layout')
@use('App\Support\Fmt')
@use('App\Services\StockTrace')
@section('title', $distributor->business_name)
@section('crumb')<a href="{{ route('erp.distributors') }}">Distributors</a>@endsection
@section('subtitle')
  <x-erp.status :value="$distributor->status"/> <span class="ms-2">{{ $distributor->area ?: 'No territory set' }} · Distributor #{{ $distributor->id }}</span>
@endsection
@php($t = $metrics['totals'])
@section('actions')
  <a href="{{ route('erp.transfers', ['new' => 1, 'to' => $loc->id]) }}" class="btn btn-primary"><x-icon name="send" size="sm"/>Allocate stock</a>
  <a href="{{ route('erp.transfers', ['new' => 1, 'from' => $loc->id]) }}" class="btn btn-light"><x-icon name="truck" size="sm"/>Transfer out</a>
  <button type="button" class="btn btn-light" data-open="stock-op"><x-icon name="edit" size="sm"/>Damage / adjust</button>
@endsection
@section('content')
@unless($metrics['reconciled'])
  <div class="alert alert-danger mb-3" role="alert"><x-icon name="alert" size="sm"/><div><b>Stock mismatch.</b> A balance at {{ $loc->name }} differs from the ledger. Check the <a href="{{ route('erp.ledger') }}">Stock Ledger</a> before posting more entries.</div></div>
@endunless

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-4 col-xxl-2"><x-erp.stat label="Total allocated" :value="Fmt::num($t['allocated'])" icon="inbox" meta="Sent to this distributor"/></div>
  <div class="col-6 col-lg-4 col-xxl-2"><x-erp.stat label="Available now" :value="Fmt::num($t['available'])" icon="box" tone="ok">{{ Fmt::num($t['on_hand']) }} on hand · {{ Fmt::num($t['reserved']) }} reserved</x-erp.stat></div>
  <div class="col-6 col-lg-4 col-xxl-2"><x-erp.stat label="Sold / distributed" :value="Fmt::num($t['sold'])" icon="cart" tone="violet" meta="Delivered to shops"/></div>
  <div class="col-6 col-lg-4 col-xxl-2"><x-erp.stat label="In transit" :value="Fmt::num($t['transit_in'])" icon="truck" tone="warn">@if($t['transit_out']){{ Fmt::num($t['transit_out']) }} going out @else On the way here @endif</x-erp.stat></div>
  <div class="col-6 col-lg-4 col-xxl-2"><x-erp.stat label="Returned" :value="Fmt::num($t['returned'])" icon="undo" tone="warn" meta="Back from shops"/></div>
  <div class="col-6 col-lg-4 col-xxl-2"><x-erp.stat label="Damaged / missing" :value="Fmt::num($t['damaged'] + $t['missing'])" icon="alert" tone="bad">{{ Fmt::num($t['damaged']) }} damaged · {{ Fmt::num($t['missing']) }} missing</x-erp.stat></div>
</div>

<div class="seg mb-3" role="tablist" aria-label="Distributor sections">
  @foreach(['overview' => 'Overview', 'movements' => 'Stock movements', 'transfers' => 'Transfers', 'returns' => 'Returns', 'activity' => 'Activity & audit'] as $k => $l)
    <a href="{{ route('erp.distributors.show', ['distributor' => $distributor, 'tab' => $k]) }}" class="{{ $tab === $k ? 'on' : '' }}" @if($tab === $k) aria-current="page" @endif>{{ $l }}</a>
  @endforeach
</div>

@if($tab === 'overview')
  <div class="row g-3">
    <div class="col-12">
      <section class="panel"><div class="panel-head"><div><h2>Distributor details</h2></div></div>
        <div class="panel-body"><div class="row g-3">
          <div class="col-lg-6"><dl class="kv">
            <dt>Distributor ID</dt><dd class="mono">#{{ $distributor->id }}</dd>
            <dt>Business name</dt><dd>{{ $distributor->business_name }}</dd>
            <dt>Contact person</dt><dd>{{ $distributor->contact_person ?: '—' }}</dd>
            <dt>Phone</dt><dd>@if($distributor->phone)<a href="tel:{{ $distributor->phone }}">{{ $distributor->phone }}</a>@else — @endif</dd>
            <dt>Email</dt><dd>@if($distributor->email)<a href="mailto:{{ $distributor->email }}">{{ $distributor->email }}</a>@else — @endif</dd>
            <dt>Territory</dt><dd>{{ $distributor->area ?: '—' }}</dd>
          </dl></div>
          <div class="col-lg-6"><dl class="kv">
            <dt>Address</dt><dd>{{ $distributor->address ?: '—' }}</dd>
            <dt>Status</dt><dd><x-erp.status :value="$distributor->status"/></dd>
            <dt>Registered</dt><dd>{{ $distributor->created_at?->format('d M Y') ?? '—' }}</dd>
            <dt>Credit limit</dt><dd>{{ $distributor->credit_limit > 0 ? Fmt::money($distributor->credit_limit, 0) : '—' }}</dd>
            <dt>Shops / users</dt><dd>{{ $distributor->shops_count }} shops · {{ $distributor->users->count() }} users</dd>
            <dt>Stock location</dt><dd>{{ $loc->name }}</dd>
          </dl></div>
        </div></div>
      </section>
    </div>
    <div class="col-12">
      <section class="panel">
        <div class="panel-head"><div><h2>Stock by product</h2><p class="hint">Open a product to see its full history here and where it sits across the network</p></div>
          <div class="d-flex gap-2 no-print">
            <a class="btn btn-sm btn-light" data-no-load href="{{ route('erp.distributors.export', [$distributor, 'report' => 'products', 'format' => 'xlsx']) }}"><x-icon name="download" size="sm"/>Excel</a>
            <a class="btn btn-sm btn-light" href="{{ route('erp.distributors.export', [$distributor, 'report' => 'products', 'format' => 'pdf']) }}"><x-icon name="printer" size="sm"/>PDF</a>
          </div></div>
        @if($metrics['products']->count())
        <div class="toolbar"><div class="search-input grow"><x-icon name="search" size="sm"/><input class="form-control" placeholder="Search products…" data-table-search="#stock-products" aria-label="Search products"></div></div>
        <div class="table-wrap"><table class="table table-hover" id="stock-products">
          <thead><tr><th data-sort>Product</th><th class="text-end" data-sort="num">Allocated</th><th class="text-end" data-sort="num">Sold</th><th class="text-end" data-sort="num">Returned</th><th class="text-end" data-sort="num">Damaged / missing</th><th class="text-end" data-sort="num">In transit</th><th class="text-end" data-sort="num">On hand</th><th class="text-end" data-sort="num">Reserved</th><th class="text-end" data-sort="num">Available</th></tr></thead>
          <tbody>
          @foreach($metrics['products'] as $p)
            <tr>
              <td><a href="{{ route('erp.distributors.show', [$distributor, 'tab' => 'movements', 'product_id' => $p['product']->id]) }}" class="cell-main">{{ $p['product']->name }}</a><span class="cell-sub mono">{{ $p['product']->sku }}</span></td>
              <td class="text-end num">{{ Fmt::num($p['allocated']) }}</td><td class="text-end num">{{ Fmt::num($p['sold']) }}</td><td class="text-end num">{{ Fmt::num($p['returned']) }}</td>
              <td class="text-end num">{{ Fmt::num($p['damaged'] + $p['missing']) }}</td><td class="text-end num">{{ Fmt::num($p['transit_in']) }}</td>
              <td class="text-end num">{{ Fmt::num($p['on_hand']) }}</td><td class="text-end num text-muted">{{ Fmt::num($p['reserved']) }}</td><td class="text-end num fw-semibold">{{ Fmt::num($p['available']) }}</td>
            </tr>
          @endforeach
          <tr class="no-results"><td colspan="9"><x-erp.empty icon="search" title="No products match"/></td></tr>
          </tbody>
        </table></div>
        @else
          <x-erp.empty icon="box" title="No stock yet" text="Allocate stock from a warehouse to start this distributor's history.">
            <a href="{{ route('erp.transfers', ['new' => 1, 'to' => $loc->id]) }}" class="btn btn-primary"><x-icon name="send" size="sm"/>Allocate stock</a>
          </x-erp.empty>
        @endif
      </section>
    </div>
  </div>
@elseif($tab === 'movements')
  @php($filtered = request()->hasAny(['q', 'product_id', 'kind', 'from', 'to']))
  @if($network->count())
    <section class="panel mb-3"><div class="panel-head"><div><h2>Where {{ $products->firstWhere('id', (int) request('product_id'))?->name }} is now</h2><p class="hint">Live balance at every location in the network</p></div></div>
      <div class="panel-body d-flex flex-wrap gap-2">
        @foreach($network as $b)<span class="tag">{{ $b->location->name }} · {{ Fmt::num($b->qty) }}</span>@endforeach
      </div>
    </section>
  @endif
  <section class="panel">
    <form class="toolbar" method="get">
      <input type="hidden" name="tab" value="movements">
      <div class="search-input grow"><x-icon name="search" size="sm"/><input class="form-control" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Search product, SKU or reference…" aria-label="Search movements"></div>
      <select name="product_id" class="form-select" style="max-width:220px" aria-label="Product"><option value="">All products</option>@foreach($products as $p)<option value="{{ $p->id }}" @selected(request('product_id') == $p->id)>{{ $p->name }}</option>@endforeach</select>
      <select name="kind" class="form-select" style="max-width:170px" aria-label="Movement type"><option value="">All types</option>@foreach(StockTrace::KINDS as $k => $v)<option value="{{ $k }}" @selected(request('kind') === $k)>{{ $v[0] }}</option>@endforeach</select>
      <input type="date" name="from" value="{{ request('from') }}" class="form-control" style="max-width:160px" aria-label="From date">
      <input type="date" name="to" value="{{ request('to') }}" class="form-control" style="max-width:160px" aria-label="To date">
      <button class="btn btn-light"><x-icon name="filter" size="sm"/>Apply</button>
      @if($filtered)<a href="{{ route('erp.distributors.show', [$distributor, 'tab' => 'movements']) }}" class="btn btn-ghost">Reset</a>@endif
      <a class="btn btn-light" data-no-load href="{{ route('erp.distributors.export', [$distributor, 'report' => 'movements', 'format' => 'xlsx'] + request()->only('q', 'product_id', 'kind', 'from', 'to')) }}"><x-icon name="download" size="sm"/>Excel</a>
      <a class="btn btn-light" href="{{ route('erp.distributors.export', [$distributor, 'report' => 'movements', 'format' => 'pdf'] + request()->only('q', 'product_id', 'kind', 'from', 'to')) }}"><x-icon name="printer" size="sm"/>PDF</a>
    </form>
    @if($rows->count())
    <div class="table-wrap"><table class="table table-hover">
      <thead><tr><th>Date</th><th>Product</th><th>Type</th><th>Source</th><th>Destination</th><th class="text-end">Qty</th><th class="text-end">Remaining</th><th>Reference</th><th>Status</th><th>By</th><th><span class="sr-only">Actions</span></th></tr></thead>
      <tbody>
      @foreach($rows as $x)
        <tr>
          <td class="text-nowrap">{{ $x->m->created_at->format('d M Y') }}<span class="cell-sub">{{ $x->m->created_at->format('H:i') }}</span></td>
          <td><span class="cell-main">{{ $x->m->product->name }}</span><span class="cell-sub mono">{{ $x->m->product->sku }}</span></td>
          <td><span class="badge-s {{ $x->kindTone }}"><span class="dot"></span>{{ $x->kindLabel }}</span><span class="cell-sub">{{ $x->typeLabel }}</span></td>
          <td>{{ $x->source }}</td>
          <td>{{ $x->destination }}</td>
          <td class="text-end num fw-semibold">@if($x->signed === null){{ Fmt::num($x->m->qty) }}@else{{ $x->signed > 0 ? '+' : '−' }}{{ Fmt::num(abs($x->signed)) }}@endif</td>
          <td class="text-end num">{{ $x->remaining === null ? '—' : Fmt::num($x->remaining) }}</td>
          <td><span class="mono">{{ $x->reference }}</span>@if($x->invoice)<span class="cell-sub mono">{{ $x->invoice->number }}</span>@endif</td>
          <td><span class="badge-s {{ $x->statusTone }}"><span class="dot"></span>{{ $x->statusLabel }}</span></td>
          <td>{{ $x->m->user?->name ?? 'System' }}</td>
          <td class="actions"><a href="{{ route('erp.ledger.show', $x->m) }}" class="btn btn-sm btn-ghost" aria-label="View movement {{ $x->m->id }}" data-tip="View details|Full transaction and trace"><x-icon name="eye" size="sm"/></a></td>
        </tr>
      @endforeach
      </tbody>
    </table></div>
    <div class="pager">{{ $rows->links() }}</div>
    @else
      <x-erp.empty icon="ledger" :title="$filtered ? 'No movements match these filters' : 'No stock movements yet'" text="Allocations, deliveries, returns and adjustments for this distributor appear here automatically."/>
    @endif
  </section>
@elseif($tab === 'transfers')
  <section class="panel">
    <div class="panel-head"><div><h2>Transfers in and out</h2><p class="hint">Approve, dispatch and receive from the Transfers page</p></div><a href="{{ route('erp.transfers') }}" class="small no-print">Open Transfers</a></div>
    @if($transfers->count())
    <div class="table-wrap"><table class="table table-hover">
      <thead><tr><th>Number / challan</th><th>Direction</th><th>From → To</th><th>Products</th><th class="text-end">Sent</th><th class="text-end">Received</th><th class="text-end">Damaged</th><th>Dispatched</th><th>Delivered</th><th>Status</th></tr></thead>
      <tbody>
      @foreach($transfers as $tr)
        <tr>
          <td class="mono">{{ $tr->number }}</td>
          <td>{{ (int) $tr->to_location_id === (int) $loc->id ? 'Incoming' : 'Outgoing' }}</td>
          <td>{{ $tr->from->name }} <x-icon name="arrow-right" size="sm" class="text-muted"/> {{ $tr->to->name }}</td>
          <td>{{ $tr->lines->map(fn ($l) => $l->product->name)->implode(', ') }}</td>
          <td class="text-end num">{{ Fmt::num($tr->lines->sum('qty')) }}</td><td class="text-end num">{{ Fmt::num($tr->lines->sum('received_qty')) }}</td><td class="text-end num">{{ Fmt::num($tr->lines->sum('damaged_qty')) }}</td>
          <td class="text-nowrap">{{ $tr->dispatched_at?->format('d M Y H:i') ?? '—' }}</td><td class="text-nowrap">{{ $tr->received_at?->format('d M Y H:i') ?? '—' }}</td>
          <td><x-erp.status :value="$tr->status"/></td>
        </tr>
      @endforeach
      </tbody>
    </table></div>
    <div class="pager">{{ $transfers->links() }}</div>
    @else
      <x-erp.empty icon="truck" title="No transfers yet" text="Stock sent to or from this distributor appears here."/>
    @endif
  </section>
@elseif($tab === 'returns')
  <section class="panel">
    <div class="panel-head"><div><h2>Receive returned stock</h2><p class="hint">Delivered orders that can still be returned. A credit note is issued automatically.</p></div>
      @if($firstWarehouse)<a href="{{ route('erp.transfers', ['new' => 1, 'from' => $loc->id, 'to' => $firstWarehouse->id]) }}" class="btn btn-sm btn-light no-print"><x-icon name="undo" size="sm"/>Return to warehouse</a>@endif</div>
    @if($orders->count())
    <div class="table-wrap"><table class="table table-hover">
      <thead><tr><th>Order</th><th>Invoice</th><th>Shop</th><th>Products</th><th class="text-end">Delivered</th><th class="text-end">Returned</th><th><span class="sr-only">Actions</span></th></tr></thead>
      <tbody>
      @foreach($orders as $o)
        <tr>
          <td class="mono">{{ $o->number }}</td><td><a class="mono" href="{{ route('erp.invoice', $o->invoice) }}">{{ $o->invoice->number }}</a></td><td>{{ $o->shop->name }}</td>
          <td>{{ $o->lines->filter(fn ($l) => $l->delivered_qty > $l->returned_qty)->map(fn ($l) => $l->product->name)->implode(', ') }}</td>
          <td class="text-end num">{{ Fmt::num($o->lines->sum('delivered_qty')) }}</td><td class="text-end num">{{ Fmt::num($o->lines->sum('returned_qty')) }}</td>
          <td class="actions"><button type="button" class="btn btn-sm btn-light" data-open="return-{{ $o->id }}"><x-icon name="undo" size="sm"/>Receive return</button></td>
        </tr>
      @endforeach
      </tbody>
    </table></div>
    <div class="pager">{{ $orders->links() }}</div>
    @else
      <x-erp.empty icon="undo" title="Nothing to return" text="Delivered orders with returnable quantity appear here."/>
    @endif
  </section>
  @foreach($orders as $o)
    <x-erp.modal :id="'return-'.$o->id" :title="'Process return · '.$o->number" subtitle="After inspection: sellable units return to distributor stock, damaged units to the damage bin. A credit note is issued automatically."
      :action="route('erp.orders.action', [$o, 'return'])" submit="Post return & credit note" confirm="Post this inspected return? Stock and a credit note are posted immediately.">
      <div class="row g-3">
        <x-erp.field :form="'return-'.$o->id" class="col-12" name="line_id" label="Product" required :id="'rl-'.$o->id">
          <select id="rl-{{ $o->id }}" name="line_id" class="form-select">@foreach($o->lines->filter(fn ($l) => $l->delivered_qty > $l->returned_qty) as $l)<option value="{{ $l->id }}">{{ $l->product->name }} — {{ $l->delivered_qty - $l->returned_qty }} can be returned</option>@endforeach</select>
        </x-erp.field>
        <x-erp.field :form="'return-'.$o->id" class="col-6" name="sellable" label="Good, back to stock" type="number" min="0" value="0" required/>
        <x-erp.field :form="'return-'.$o->id" class="col-6" name="damaged" label="Damaged" type="number" min="0" value="0" required/>
        <x-erp.field :form="'return-'.$o->id" class="col-12" name="reason" label="Return reason" required maxlength="255" placeholder="Near expiry · wrong item · excess supply"/>
      </div>
    </x-erp.modal>
  @endforeach
@else
  <section class="panel">
    <form class="toolbar" method="get">
      <input type="hidden" name="tab" value="activity">
      <input type="date" name="from" value="{{ request('from') }}" class="form-control" style="max-width:160px" aria-label="From date">
      <input type="date" name="to" value="{{ request('to') }}" class="form-control" style="max-width:160px" aria-label="To date">
      <button class="btn btn-light"><x-icon name="filter" size="sm"/>Apply</button>
      @if(request()->hasAny(['from', 'to']))<a href="{{ route('erp.distributors.show', [$distributor, 'tab' => 'activity']) }}" class="btn btn-ghost">Reset</a>@endif
      <span class="ms-auto d-flex gap-2">
        <a class="btn btn-light" data-no-load href="{{ route('erp.distributors.export', [$distributor, 'report' => 'activity', 'format' => 'xlsx'] + request()->only('from', 'to')) }}"><x-icon name="download" size="sm"/>Excel</a>
        <a class="btn btn-light" href="{{ route('erp.distributors.export', [$distributor, 'report' => 'activity', 'format' => 'pdf'] + request()->only('from', 'to')) }}"><x-icon name="printer" size="sm"/>PDF</a>
      </span>
    </form>
    @if($logs->count())
    <ol class="timeline">
      @foreach($logs as $l)
        <li>
          <div class="when">{{ $l->created_at->format('d M Y, H:i') }}</div>
          <div class="what"><b>{{ Str::headline(str_replace('.', ' ', $l->action)) }}</b> by {{ $l->user?->name ?? 'System' }}@if($l->entity === 'StockMovement') · <a href="{{ route('erp.ledger.show', $l->entity_id) }}">Entry #{{ $l->entity_id }}</a>@endif</div>
          @if($l->data)<div class="cell-sub">{{ StockTrace::auditSummary($l->data) }}</div>@endif
        </li>
      @endforeach
    </ol>
    <div class="pager">{{ $logs->links() }}</div>
    @else
      <x-erp.empty icon="activity" title="No activity recorded" text="Every stock action for this distributor is logged here with who did it and when."/>
    @endif
  </section>
@endif

<x-erp.modal id="stock-op" title="Damage, missing or adjustment" :subtitle="'Posts a permanent ledger entry at '.$loc->name.'. A wrong entry is undone with a reversal, never edited.'" :action="route('erp.distributors.stock', $distributor)" submit="Post entry" confirm="Post this stock entry? It is permanently recorded with your name.">
  <input type="hidden" name="key" value="{{ Str::uuid() }}">
  <div class="row g-3">
    <x-erp.field form="stock-op" class="col-12" name="kind" label="What happened" required id="op-kind">
      <select id="op-kind" name="kind" class="form-select">@foreach(['damaged' => 'Damaged stock', 'missing' => 'Missing stock', 'decrease' => 'Adjust down (count is lower)', 'increase' => 'Adjust up (count is higher)'] as $k => $l)<option value="{{ $k }}" @selected(old('kind') === $k)>{{ $l }}</option>@endforeach</select>
    </x-erp.field>
    <x-erp.field form="stock-op" class="col-sm-8" name="product_id" label="Product" required id="op-product">
      <select id="op-product" name="product_id" class="form-select">@foreach($products as $p)<option value="{{ $p->id }}" @selected(old('product_id') == $p->id)>{{ $p->name }} — {{ $free[$p->id] ?? 0 }} free</option>@endforeach</select>
    </x-erp.field>
    <x-erp.field form="stock-op" class="col-sm-4" name="qty" label="Quantity" type="number" min="1" step="1" required/>
    <x-erp.field form="stock-op" class="col-12" name="reason" label="Reason" required maxlength="255" placeholder="Broken in storage · Physical count on 5 Nov"/>
  </div>
</x-erp.modal>
@endsection
