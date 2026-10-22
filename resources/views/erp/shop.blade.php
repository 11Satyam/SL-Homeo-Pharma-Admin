@extends('erp.layout')
@use('App\Support\Fmt')
@section('title', $shop->name)
@section('crumb')<a href="{{ route('erp.shops') }}">Shops</a>@endsection
@section('subtitle')
  <x-erp.status :value="$shop->status"/> <span class="ms-2">{{ $shop->area }} · {{ $shop->distributor->business_name }}</span>
@endsection
@php($canCollect = auth()->user()->hasRole('admin', 'distributor', 'staff'))
@php($open = $invoices->filter(fn ($i) => (float) $i->outstanding() > 0.004))
@php($overdue = $open->filter(fn ($i) => $i->due_date->lt(today())))
@php($used = $shop->credit_limit > 0 ? $outstanding / $shop->credit_limit * 100 : null)
@section('actions')
  @if(auth()->user()->hasRole('admin', 'distributor', 'staff'))<a href="{{ route('erp.orders', ['shop' => $shop->id, 'new' => 1]) }}" class="btn btn-light"><x-icon name="cart" size="sm"/>New order</a>@endif
  @if($canCollect && $open->count())<a href="#collect" class="btn btn-primary" data-no-load><x-icon name="wallet" size="sm"/>Record collection</a>@endif
@endsection
@section('content')
<div class="row g-3 mb-3">
  <div class="col-md-4"><x-erp.stat label="Outstanding balance" :value="Fmt::money($outstanding)" icon="wallet" :tone="$overdue->count() ? 'bad' : 'warn'">
    @if($overdue->count())<span class="down">{{ Fmt::money($overdue->sum(fn ($i) => (float) $i->outstanding()), 0) }} overdue</span>@else {{ $open->count() }} open {{ Str::plural('invoice', $open->count()) }} @endif</x-erp.stat></div>
  <div class="col-md-4"><div class="stat"><div class="label"><span class="tile-ic"><x-icon name="trend" size="sm"/></span>Credit limit</div>
    <div class="value">{{ $shop->credit_limit > 0 ? Fmt::money($shop->credit_limit, 0) : 'Not set' }}</div>
    @if($used !== null)<div class="meter mt-2 {{ $used > 100 ? 'bad' : ($used > 75 ? 'warn' : '') }}"><span style="width: {{ min(100, $used) }}%"></span></div><div class="meta">{{ round($used) }}% used · {{ $shop->payment_terms_days }}-day terms</div>@else<div class="meta">{{ $shop->payment_terms_days }}-day payment terms</div>@endif</div></div>
  <div class="col-md-4"><div class="stat"><div class="label"><span class="tile-ic violet"><x-icon name="user" size="sm"/></span>Contact</div>
    <dl class="kv mt-2"><dt>Owner</dt><dd>{{ $shop->owner ?: '—' }}</dd><dt>Phone</dt><dd>@if($shop->phone)<a href="tel:{{ $shop->phone }}">{{ $shop->phone }}</a>@else — @endif</dd><dt>Address</dt><dd class="fw-normal">{{ $shop->address ?: '—' }}</dd></dl></div></div>
</div>

<div class="row g-3">
  <div class="col-xl-7">
    <section class="panel mb-3"><div class="panel-head"><div><h2>Invoices</h2><p class="hint">Oldest unpaid first is how automatic allocation applies collections</p></div></div>
      @if($invoices->count())
      <div class="table-wrap"><table class="table table-hover">
        <thead><tr><th>Invoice</th><th>Issued</th><th>Due</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Credits</th><th class="text-end">Outstanding</th></tr></thead>
        <tbody>@foreach($invoices as $i)@php($o = (float) $i->outstanding())
          <tr class="{{ $i->due_date->lt(today()) && $o > 0.004 ? 'row-danger' : '' }}"><td><a href="{{ route('erp.invoice', $i) }}" class="mono">{{ $i->number }}</a></td><td>{{ $i->issue_date->format('d M y') }}</td><td>{{ $i->due_date->format('d M y') }}</td>
            <td class="text-end num">{{ Fmt::money($i->total) }}</td><td class="text-end num text-muted">{{ Fmt::money($i->allocations->sum('amount')) }}</td><td class="text-end num text-muted">{{ Fmt::money($i->creditNotes->sum('amount')) }}</td>
            <td class="text-end num fw-semibold">@if($o > 0.004){{ Fmt::money($o) }}@else<x-erp.status value="paid"/>@endif</td></tr>
        @endforeach</tbody>
      </table></div>
      @else<x-erp.empty icon="file" title="No invoices yet" text="Invoices are issued when this shop’s orders are delivered."/>@endif
    </section>

    <section class="panel"><div class="panel-head"><div><h2>Recent orders</h2></div><a href="{{ route('erp.orders', ['q' => $shop->name]) }}" class="small">All orders</a></div>
      @if($orders->count())
      <div class="table-wrap"><table class="table table-hover"><thead><tr><th>Order</th><th>Date</th><th>Staff</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
        <tbody>@foreach($orders as $o)<tr><td><a href="{{ route('erp.orders') }}#order-{{ $o->id }}" class="mono">{{ $o->number }}</a></td><td>{{ $o->created_at->format('d M y') }}</td><td>{{ $o->staff?->name ?? '—' }}</td><td class="text-end num">{{ Fmt::money($o->total) }}</td><td><x-erp.status :value="$o->status"/></td></tr>@endforeach</tbody></table></div>
      @else<x-erp.empty icon="cart" title="No orders yet"/>@endif
    </section>
  </div>

  <div class="col-xl-5">
    @if($canCollect && $open->count())
    <section class="panel mb-3" id="collect" tabindex="-1">
      <div class="panel-head"><div><h2>Record collection</h2><p class="hint">Stays “Pending verification” until accounts confirm the deposit</p></div></div>
      <form method="post" action="{{ route('erp.payments.store') }}" class="panel-body">@csrf
        <input type="hidden" name="shop_id" value="{{ $shop->id }}"><input type="hidden" name="key" value="{{ Str::uuid() }}">
        <div class="row g-3">
          <x-erp.field class="col-sm-6" name="amount" label="Amount (₹)" type="number" step="0.01" min="0.01" required :placeholder="number_format((float) $outstanding, 2, '.', '')"/>
          <x-erp.field class="col-sm-6" name="method" label="Method" required id="pay-method">
            <select id="pay-method" name="method" class="form-select">@foreach(\App\Models\Payment::METHODS as $m)<option value="{{ $m }}" @selected(old('method', 'cash') === $m)>{{ Fmt::label($m) }}</option>@endforeach</select>
          </x-erp.field>
          <x-erp.field class="col-12" name="reference" label="Reference / UTR / cheque no." maxlength="100" id="pay-ref" data-require-if="#pay-method:{{ implode(',', config('fieldapp.collections.reference_required', [])) }}" placeholder="Required for UPI, bank transfer and cheque"/>
          <div class="col-12">
            <input type="hidden" name="auto" value="0">
            <label class="form-check"><input class="form-check-input" type="checkbox" name="auto" value="1" id="pay-auto" @checked(old('auto', '1') === '1')> <span class="form-check-label">Apply to oldest unpaid invoices automatically</span></label>
          </div>
          <div class="col-12" data-show-if="#pay-auto:unchecked">
            <div class="form-section">Allocate manually</div>
            <div class="gap-grid" style="gap:.5rem">
              @foreach($open as $i)<div class="input-group input-group-sm"><span class="input-group-text flex-fill justify-content-between"><span class="mono">{{ $i->number }}</span><span class="text-muted">due {{ Fmt::money($i->outstanding()) }}</span></span>
                <input name="alloc[{{ $i->id }}]" type="number" step="0.01" min="0" max="{{ $i->outstanding() }}" class="form-control text-end" style="max-width:130px" placeholder="0.00" value="{{ old('alloc.'.$i->id) }}" aria-label="Allocate to {{ $i->number }}"></div>@endforeach
            </div>
          </div>
          <x-erp.field class="col-12" name="remarks" label="Remarks (optional)" maxlength="500"/>
          <div class="col-12 d-flex justify-content-end"><button class="btn btn-primary"><x-icon name="check" size="sm"/>Record collection</button></div>
        </div>
      </form>
    </section>
    @endif

    <section class="panel"><div class="panel-head"><div><h2>Payments</h2></div><span class="text-muted small">{{ $payments->count() }} received</span></div>
      @if($payments->count())
        <ul class="feed">@foreach($payments as $p)
          <li><span class="f-ic {{ $p->status === 'verified' ? 'ok' : 'warn' }}"><x-icon name="wallet" size="sm"/></span>
            <div class="f-body"><b class="num">{{ Fmt::money($p->amount) }}</b> · {{ Fmt::label($p->method) }} @if($p->reference)<span class="mono text-muted small">{{ $p->reference }}</span>@endif
              <div class="text-muted small">{{ ($p->collected_at ?? $p->created_at)->format('d M Y') }}@if($p->collector) · {{ $p->collector->name }}@endif · {{ $p->receipt_number }}</div></div>
            <x-erp.status :value="$p->status"/></li>
        @endforeach</ul>
      @else<x-erp.empty icon="wallet" title="No payments yet"/>@endif
    </section>
  </div>
</div>
@endsection
