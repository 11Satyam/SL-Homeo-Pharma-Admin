@extends('erp.layout')
@use('App\Support\Fmt')
@section('title', 'Invoices & Outstanding')
@section('crumb')<span>Sales</span>@endsection
@section('subtitle', 'Invoices are issued automatically on delivery. Outstanding = total − allocated payments − credit notes.')
@section('content')
<div class="row g-3 mb-3">
  <div class="col-sm-6 col-xl-3"><x-erp.stat label="Total invoiced" :value="Fmt::moneyShort($totals['invoiced'])" icon="file" meta="All time"/></div>
  <div class="col-sm-6 col-xl-3"><x-erp.stat label="Outstanding" :value="Fmt::moneyShort($totals['outstanding'])" icon="wallet" tone="warn" :meta="$totals['openCount'].' open invoices'" :href="route('erp.invoices', ['status' => 'unpaid'])"/></div>
  <div class="col-sm-6 col-xl-3"><x-erp.stat label="Overdue" :value="Fmt::moneyShort($totals['overdue'])" icon="alert" :tone="$totals['overdueCount'] ? 'bad' : 'ok'" :meta="$totals['overdueCount'].' past due date'" :href="route('erp.invoices', ['status' => 'overdue'])"/></div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat"><div class="label"><span class="tile-ic"><x-icon name="clock" size="sm"/></span>Aging of outstanding</div>
      @php($agingTotal = max(1, array_sum($aging)))
      <div class="stackbar mt-3" role="img" aria-label="Aging split">
        @foreach($aging as $k => $v)@if($v > 0)<span style="width: {{ $v / $agingTotal * 100 }}%; background: {{ ['Not due' => 'var(--ok-dot)', '1–30' => 'var(--warn-dot)', '31–60' => '#ec835a', '61–90' => 'var(--bad-dot)', '90+' => '#912018'][$k] }}" data-tip="{{ $k === 'Not due' ? 'Not due' : $k.' days late' }}|{{ Fmt::money($v, 0) }}"></span>@endif @endforeach
      </div>
      <div class="meta">@foreach($aging as $k => $v)@if($v > 0)<span class="tag">{{ $k }}: {{ Fmt::moneyShort($v) }}</span>@endif @endforeach @if(! array_sum($aging)) Nothing outstanding @endif</div>
    </div>
  </div>
</div>

<section class="panel">
  <form class="toolbar" method="get">
    <div class="seg">
      @foreach(['' => 'All', 'unpaid' => 'Unpaid', 'overdue' => 'Overdue', 'paid' => 'Paid'] as $k => $l)
        <a href="{{ request()->fullUrlWithQuery(['status' => $k ?: null, 'page' => null]) }}" class="{{ request('status', '') === $k ? 'on' : '' }}">{{ $l }}</a>
      @endforeach
    </div>
    <div class="search-input grow"><x-icon name="search" size="sm"/><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Invoice number or shop…  ( / )" aria-label="Search invoices"></div>
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <button class="btn btn-light">Search</button>
  </form>
  @if($invoices->count())
  <div class="table-wrap"><table class="table table-hover">
    <thead><tr><th>Invoice</th><th>Shop</th><th>Issued</th><th>Due</th><th class="text-end">Total</th><th class="text-end">Paid / credited</th><th class="text-end">Outstanding</th><th>Status</th></tr></thead>
    <tbody>
    @foreach($invoices as $i)
      @php($due = (float) $i->due)
      @php($st = $due <= 0.004 ? 'paid' : ($i->due_date->lt(today()) ? 'overdue' : ($due < (float) $i->total ? 'partial' : 'unpaid')))
      <tr>
        <td><a href="{{ route('erp.invoice', $i) }}" class="cell-main mono">{{ $i->number }}</a></td>
        <td><a href="{{ route('erp.shop', $i->shop) }}">{{ $i->shop->name }}</a><span class="cell-sub">{{ $i->shop->area }}</span></td>
        <td class="text-nowrap">{{ $i->issue_date->format('d M Y') }}</td>
        <td class="text-nowrap">{{ $i->due_date->format('d M Y') }}@if($st === 'overdue')<span class="cell-sub text-danger">{{ (int) $i->due_date->diffInDays(today()) }} days late</span>@endif</td>
        <td class="text-end num">{{ Fmt::money($i->total) }}</td>
        <td class="text-end num text-muted">{{ Fmt::money($i->allocations->sum('amount') + $i->creditNotes->sum('amount')) }}</td>
        <td class="text-end num fw-semibold">{{ Fmt::money(max(0, $due)) }}</td>
        <td><x-erp.status :value="$st"/></td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
  <div class="pager">{{ $invoices->links() }}</div>
  @else
    <x-erp.empty icon="file" :title="request()->hasAny(['q','status']) ? 'No invoices match' : 'No invoices yet'" text="Invoices are issued automatically when an order is delivered."/>
  @endif
</section>
@endsection
