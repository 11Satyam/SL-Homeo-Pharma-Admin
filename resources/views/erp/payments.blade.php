@extends('erp.layout')
@use('App\Support\Fmt')
@section('title', 'Payments & Collections')
@section('crumb')<span>Sales</span>@endsection
@section('subtitle', 'Collections are recorded from a shop statement or the field app. Money counts as deposited only after it is verified here.')
@php($canVerify = auth()->user()->hasRole('admin', 'accounts_manager', 'distributor'))
@php($canRecord = auth()->user()->hasRole('admin', 'distributor', 'staff') && $shops->count())
@section('actions')
  @if($canRecord)<button type="button" class="btn btn-primary" data-open="pick-shop"><x-icon name="plus" size="sm"/>Record payment</button>@endif
@endsection
@section('content')
@if($canRecord)
<dialog id="pick-shop" class="modal-x narrow" aria-labelledby="pick-shop-t">
  <form class="dlg-inner" onsubmit="event.preventDefault();var s=this.shop.value;if(s){document.body.classList.add('is-loading');location.href=s+'#collect'}">
    <div class="dlg-head"><div><h2 id="pick-shop-t">Record a payment</h2><p>Collections are recorded against a shop so they can be applied to its invoices.</p></div>
      <button type="button" class="icon-btn" data-close aria-label="Close"><x-icon name="x"/></button></div>
    <div class="dlg-body">
      <label class="form-label" for="pick-shop-sel">Shop<span class="req">*</span></label>
      <select id="pick-shop-sel" name="shop" class="form-select" required><option value="">Select shop…</option>@foreach($shops as $s)<option value="{{ route('erp.shop', $s) }}">{{ $s->name }}{{ $s->area ? ' · '.$s->area : '' }}</option>@endforeach</select>
    </div>
    <div class="dlg-foot"><button type="button" class="btn btn-light" data-close>Cancel</button><button class="btn btn-primary">Continue<x-icon name="arrow-right" size="sm"/></button></div>
  </form>
</dialog>
@endif
<div class="row g-3 mb-3">
  <div class="col-sm-4"><x-erp.stat label="Awaiting verification" :value="Fmt::money($totals['pending'], 0)" icon="clock" tone="warn" :meta="$totals['pendingCount'].' collections with staff'" :href="route('erp.payments', ['status' => 'collected'])"/></div>
  <div class="col-sm-4"><x-erp.stat label="Verified (deposited)" :value="Fmt::money($totals['verified'], 0)" icon="check-circle" tone="ok" meta="All time" :href="route('erp.payments', ['status' => 'verified'])"/></div>
  <div class="col-sm-4"><x-erp.stat label="Collected this month" :value="Fmt::money($totals['month'], 0)" icon="wallet" :meta="now()->format('F Y')"/></div>
</div>
<section class="panel">
  <form class="toolbar" method="get">
    <div class="seg">
      @foreach(['' => 'All', 'collected' => 'Pending verification', 'verified' => 'Verified'] as $k => $l)
        <a href="{{ request()->fullUrlWithQuery(['status' => $k ?: null, 'page' => null]) }}" class="{{ request('status', '') === $k ? 'on' : '' }}">{{ $l }}</a>
      @endforeach
    </div>
    <div class="search-input grow"><x-icon name="search" size="sm"/><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Shop, receipt no. or UTR…  ( / )" aria-label="Search payments"></div>
    <select name="method" class="form-select" style="max-width:170px" aria-label="Method" onchange="this.form.requestSubmit()"><option value="">All methods</option>@foreach(\App\Models\Payment::METHODS as $m)<option value="{{ $m }}" @selected(request('method') === $m)>{{ Fmt::label($m) }}</option>@endforeach</select>
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <button class="btn btn-light">Search</button>
  </form>
  @if($payments->count())
  <div class="table-wrap"><table class="table table-hover">
    <thead><tr><th>Collected</th><th>Receipt</th><th>Shop</th><th>Collected by</th><th>Method</th><th class="text-end">Amount</th><th class="text-end">Allocated</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    @foreach($payments as $p)
      @php($alloc = (float) $p->allocations->sum('amount'))
      <tr>
        <td class="text-nowrap">{{ ($p->collected_at ?? $p->created_at)->format('d M Y') }}<span class="cell-sub">{{ ($p->collected_at ?? $p->created_at)->format('H:i') }}</span></td>
        <td><span class="mono">{{ $p->receipt_number ?? '—' }}</span></td>
        <td><a href="{{ route('erp.shop', $p->shop) }}" class="fw-semibold">{{ $p->shop->name }}</a>@if($p->remarks)<span class="cell-sub">{{ Str::limit($p->remarks, 40) }}</span>@endif</td>
        <td>@if($p->collector)<span class="d-inline-flex align-items-center gap-2"><span class="avatar sm" style="background: {{ Fmt::avatarColor($p->collector->name) }}">{{ Fmt::initials($p->collector->name) }}</span>{{ $p->collector->name }}</span>@endif</td>
        <td><span class="tag">{{ Fmt::label($p->method) }}</span>@if($p->reference)<span class="cell-sub mono">{{ $p->reference }}</span>@endif</td>
        <td class="text-end num fw-semibold">{{ Fmt::money($p->amount) }}</td>
        <td class="text-end num">{{ Fmt::money($alloc) }}@if($p->amount - $alloc > 0.004)<span class="cell-sub">{{ Fmt::money($p->amount - $alloc) }} unapplied</span>@endif</td>
        <td><x-erp.status :value="$p->status"/>@if($p->verified_at)<span class="cell-sub">{{ $p->verified_at->format('d M') }}</span>@endif</td>
        <td class="actions">@if($p->status === 'collected' && $canVerify)
          <form method="post" action="{{ route('erp.payments.verify', $p) }}" data-confirm="Confirm {{ Fmt::money($p->amount) }} from {{ $p->shop->name }} has been received in the bank / cash book?" data-confirm-title="Verify collection" data-confirm-ok="Yes, verify">@csrf<button class="btn btn-sm btn-outline-success"><x-icon name="check" size="sm"/>Verify</button></form>
        @endif</td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
  <div class="pager">{{ $payments->links() }}</div>
  @else
    <x-erp.empty icon="wallet" :title="request()->hasAny(['q','status','method']) ? 'No payments match' : 'No collections yet'" text="Record a collection from a shop’s statement page, or from the field staff app."/>
  @endif
</section>
@endsection
