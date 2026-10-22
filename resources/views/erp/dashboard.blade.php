@extends('erp.layout')
@use('App\Support\Fmt')
@section('title', 'Dashboard')
@php
  $delta = $stats['salesPrev30'] > 0 ? round(($stats['sales30'] - $stats['salesPrev30']) / $stats['salesPrev30'] * 100, 1) : null;
  $stockTotal = max(1, array_sum($stock));
  $toneOf = ['confirmed' => '', 'packed' => '', 'out_for_delivery' => 'warn', 'delivered' => 'ok', 'partially_delivered' => 'warn', 'failed' => 'bad', 'cancelled' => ''];
@endphp
@section('content')
@php($me = auth()->user())
@php($hour = (int) now()->setTimezone(\App\Support\BusinessDay::tz())->format('G'))
<section class="hero-banner mb-3">
  <div class="hb-text">
    <div class="hb-date">{{ now()->setTimezone(\App\Support\BusinessDay::tz())->format('l, d F Y') }}</div>
    <h2>{{ $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening') }}, {{ Str::before($me->name, ' ') }} 👋</h2>
    <p>
      @if($stats['openOrders'] || $stats['overdueCount'] || $stats['unverifiedCount'])
        You have <b>{{ $stats['openOrders'] }}</b> open {{ Str::plural('order', $stats['openOrders']) }},
        <b>{{ $stats['overdueCount'] }}</b> overdue {{ Str::plural('invoice', $stats['overdueCount']) }} and
        <b>{{ $stats['unverifiedCount'] }}</b> {{ Str::plural('collection', $stats['unverifiedCount']) }} to verify.
      @else
        Everything is up to date. Here’s how the business is doing.
      @endif
    </p>
  </div>
  @if(class_exists(\App\Support\Guide::class))
    <div class="hb-actions">
      @foreach(array_slice(\App\Support\Guide::actions($me), 0, 4) as [$label, $url, $primary])
        <a href="{{ $url }}" class="hb-btn {{ $primary ? 'primary' : '' }}">{{ $label }}<x-icon name="arrow-right" size="sm"/></a>
      @endforeach
    </div>
  @endif
</section>
{{-- KPI row --}}
<div class="row g-3 mb-3">
  <div class="col-sm-6 col-xl-3">
    <x-erp.stat label="Sales · last 30 days" class="c-blue" :value="Fmt::moneyShort($stats['sales30'])" icon="trend" :href="route('erp.invoices')">
      @if($delta !== null)<span class="{{ $delta >= 0 ? 'up' : 'down' }}"><x-icon :name="$delta >= 0 ? 'up' : 'down'" size="sm"/>{{ abs($delta) }}%</span> vs previous 30 days
      @else Invoiced on delivery @endif
    </x-erp.stat>
  </div>
  <div class="col-sm-6 col-xl-3">
    <x-erp.stat label="Outstanding receivables" class="c-amber" :value="Fmt::moneyShort($stats['outstanding'])" icon="file" :tone="$stats['overdueCount'] ? 'bad' : ''" :href="route('erp.invoices')">
      @if($stats['overdueCount'])<span class="down">{{ Fmt::moneyShort($stats['overdueAmount']) }}</span> overdue on {{ $stats['overdueCount'] }} {{ Str::plural('invoice', $stats['overdueCount']) }}@else Nothing overdue @endif
    </x-erp.stat>
  </div>
  <div class="col-sm-6 col-xl-3">
    <x-erp.stat label="Open orders" class="c-violet" :value="Fmt::num($stats['openOrders'])" icon="cart" tone="violet" :href="route('erp.orders')"
      :meta="$stats['ordersToday'].' placed today · '.Fmt::moneyShort($stats['salesToday']).' invoiced today'"/>
  </div>
  <div class="col-sm-6 col-xl-3">
    <x-erp.stat label="Collections · last 30 days" class="c-green" :value="Fmt::moneyShort($stats['collected30'])" icon="wallet" tone="ok" :href="route('erp.payments')">
      @if($stats['unverifiedCount'])<span>{{ Fmt::moneyShort($stats['unverifiedAmount']) }} awaiting verification ({{ $stats['unverifiedCount'] }})</span>@else All collections verified @endif
    </x-erp.stat>
  </div>
</div>

{{-- Trend + stock --}}
<div class="row g-3 mb-3">
  <div class="col-xl-8">
    <section class="panel h-100">
      <div class="panel-head"><div><h2>Invoiced sales</h2><p class="hint">Daily value of deliveries invoiced, last 14 days · today highlighted</p></div>
        <div class="text-end"><div class="fw-bold fs-5">{{ Fmt::money(array_sum($trend), 0) }}</div><div class="text-muted small">14-day total</div></div></div>
      <div class="panel-body pt-3">
        @if(array_sum($trend) > 0)
          <x-erp.columns :data="$trend" money title="Invoiced sales per day, last 14 days"/>
        @else
          <x-erp.empty icon="trend" title="No sales in the last 14 days" text="Invoices are issued automatically when an order is delivered."/>
        @endif
      </div>
    </section>
  </div>
  <div class="col-xl-4">
    <section class="panel h-100 d-flex flex-column">
      <div class="panel-head"><div><h2>Stock on hand</h2><p class="hint">Units by location type{{ $did ? ' (your location)' : '' }}</p></div><a href="{{ route('erp.stock') }}" class="small">View stock</a></div>
      <div class="panel-body">
        <div class="hero-figure">{{ Fmt::num(array_sum($stock)) }}</div><div class="text-muted small mb-3">units across {{ $did ? 'your distributor location' : 'all internal locations' }}</div>
        @if(! $did)
          <div class="stackbar" role="img" aria-label="Stock split: @foreach($stock as $k => $v){{ $k }} {{ $v }} units; @endforeach">
            @foreach(array_values($stock) as $i => $v)@if($v > 0)<span class="sw-{{ $i + 1 }}" style="width: {{ $v / $stockTotal * 100 }}%" data-tip="{{ array_keys($stock)[$i] }}|{{ Fmt::num($v) }} units · {{ round($v / $stockTotal * 100) }}%"></span>@endif @endforeach
          </div>
          <div class="legend">@foreach(array_keys($stock) as $i => $k)<span><i class="sw-{{ $i + 1 }}"></i>{{ $k }} <b class="num">{{ Fmt::num($stock[$k]) }}</b></span>@endforeach</div>
        @endif
      </div>
      <div class="mt-auto divide">
        @if(! $did)<a class="alert-row" href="{{ route('erp.transfers') }}"><span class="a-ic info"><x-icon name="truck" size="sm"/></span><span class="flex-fill">Transfers pending approval / dispatch</span><b class="num">{{ $stats['pendingTransfers'] }}</b></a>
        <a class="alert-row" href="{{ route('erp.ledger') }}"><span class="a-ic {{ $damaged ? 'warn' : 'ok' }}"><x-icon name="alert" size="sm"/></span><span class="flex-fill">Units written off as damaged</span><b class="num">{{ Fmt::num($damaged) }}</b></a>
        @else<a class="alert-row" href="{{ route('erp.transfers') }}"><span class="a-ic info"><x-icon name="truck" size="sm"/></span><span class="flex-fill">Inbound transfers in transit</span><b class="num">{{ $stats['pendingTransfers'] }}</b></a>@endif
      </div>
    </section>
  </div>
</div>

{{-- Operational strip --}}
<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><x-erp.stat mini label="Field staff checked in" class="c-teal" :value="$stats['checkedIn'].' / '.$stats['fieldStaff']" icon="pin" :href="auth()->user()->hasRole('admin','distributor','accounts_manager','viewer') ? route('erp.attendance') : null" meta="Today"/></div>
  <div class="col-6 col-lg-3"><x-erp.stat mini label="Expenses to review" class="c-rose" :value="$stats['expensesToReview']" icon="receipt" :tone="$stats['expensesToReview'] ? 'warn' : ''" :href="auth()->user()->hasRole('admin','distributor','accounts_manager','viewer') ? route('erp.expenses', ['status' => 'submitted']) : null" :meta="Fmt::money($stats['expensesToReviewAmount'], 0).' claimed'"/></div>
  <div class="col-6 col-lg-3"><x-erp.stat mini label="Active shops" class="c-blue" :value="Fmt::num($stats['shops'])" icon="store" :href="route('erp.shops')" :meta="$did ? 'In your territory' : 'Across '.$stats['distributors'].' distributors'"/></div>
  <div class="col-6 col-lg-3"><x-erp.stat mini label="Active products" class="c-violet" :value="Fmt::num($stats['products'])" icon="pill" :href="route('erp.products')" :meta="$lowStock->count() ? $lowStock->count().' below reorder level' : 'Stock levels healthy'"/></div>
</div>

{{-- Pipeline / aging / top products --}}
<div class="row g-3 mb-3">
  <div class="col-lg-4">
    <section class="panel h-100"><div class="panel-head"><div><h2>Order pipeline</h2><p class="hint">Orders created in the last 30 days, by status</p></div></div>
      <div class="panel-body">
        @if($pipeline->sum())
          <x-erp.hbars :rows="$pipeline->map(fn ($n, $s) => [Fmt::status($s)[1], $n, Fmt::num($n), $toneOf[$s] ?? ''])->values()->all()"/>
        @else<x-erp.empty icon="cart" title="No orders yet" text="Orders from the panel and the field app appear here."/>@endif
      </div></section>
  </div>
  <div class="col-lg-4">
    <section class="panel h-100"><div class="panel-head"><div><h2>Receivables aging</h2><p class="hint">Outstanding balance by days past due</p></div><a href="{{ route('erp.invoices') }}" class="small">Invoices</a></div>
      <div class="panel-body">
        @if(array_sum($aging) > 0)
          <x-erp.hbars :rows="collect($aging)->map(fn ($v, $k) => [$k, $v, Fmt::money($v, 0), $k === 'Not due' ? 'ok' : (in_array($k, ['61–90 days', '90+ days']) ? 'bad' : 'warn')])->values()->all()"/>
        @else<x-erp.empty icon="check-circle" title="No outstanding balance" text="Every invoice is fully paid."/>@endif
      </div></section>
  </div>
  <div class="col-lg-4">
    <section class="panel h-100"><div class="panel-head"><div><h2>Top products</h2><p class="hint">Delivered value, last 30 days</p></div></div>
      <div class="panel-body">
        @if($topProducts->count())
          <x-erp.hbars :rows="$topProducts->map(fn ($p) => [$p->name, (float) $p->value, Fmt::moneyShort($p->value).' · '.Fmt::num($p->units).' u'])->all()"/>
        @else<x-erp.empty icon="pill" title="No deliveries yet" text="Top sellers appear once orders are delivered."/>@endif
      </div></section>
  </div>
</div>

{{-- Attention + activity --}}
<div class="row g-3">
  <div class="col-lg-5">
    <section class="panel h-100"><div class="panel-head"><div><h2>Needs attention</h2><p class="hint">Exceptions that block cash or stock</p></div></div>
      <div class="divide">
        @foreach($lowStock->take(4) as $b)
          <a class="alert-row" href="{{ route('erp.stock') }}"><span class="a-ic bad"><x-icon name="alert" size="sm"/></span>
            <span class="flex-fill"><b>{{ $b->product->name }}</b> low at {{ $b->location->name }}<span class="d-block text-muted small">{{ Fmt::num($b->qty) }} on hand · reorder at {{ Fmt::num($b->product->reorder_level) }}</span></span><x-erp.status value="low"/></a>
        @endforeach
        @foreach($overdueList as $i)
          <a class="alert-row" href="{{ route('erp.invoice', $i) }}"><span class="a-ic warn"><x-icon name="file" size="sm"/></span>
            <span class="flex-fill"><b>{{ $i->number }}</b> · {{ $i->shop->name }}<span class="d-block text-muted small">Due {{ $i->due_date->format('d M') }} · {{ (int) $i->due_date->diffInDays(today()) }} days late</span></span><b class="num">{{ Fmt::money($i->due, 0) }}</b></a>
        @endforeach
        @if($stats['unverifiedCount'])
          <a class="alert-row" href="{{ route('erp.payments') }}"><span class="a-ic info"><x-icon name="wallet" size="sm"/></span><span class="flex-fill"><b>{{ $stats['unverifiedCount'] }} collections</b> awaiting deposit verification</span><x-icon name="chev-right" size="sm" class="text-muted"/></a>
        @endif
        @if($lowStock->isEmpty() && $overdueList->isEmpty() && ! $stats['unverifiedCount'])
          <x-erp.empty icon="check-circle" title="All clear" text="No low stock, overdue invoices or unverified collections."/>
        @endif
      </div></section>
  </div>
  <div class="col-lg-7">
    <section class="panel h-100"><div class="panel-head"><div><h2>Recent activity</h2><p class="hint">Orders, deliveries, collections, expenses and transfers</p></div>@if(auth()->user()->hasRole('admin', 'viewer'))<a href="{{ route('erp.audit') }}" class="small">Audit log</a>@endif</div>
      @if($activity->count())
        <ul class="feed">
          @foreach($activity as $a)
            <li><span class="f-ic {{ $a['tone'] }}"><x-icon :name="$a['icon']" size="sm"/></span>
              <div class="f-body"><a href="{{ $a['href'] }}" class="text-reset"><b>{{ $a['text'] }}</b></a><div class="text-muted small text-truncate">{{ $a['sub'] }}</div></div>
              <span class="f-time" title="{{ $a['at']->format('d M Y H:i') }}">{{ $a['at']->diffForHumans(null, true, true) }}</span></li>
          @endforeach
        </ul>
      @else<x-erp.empty icon="activity" title="No activity yet" text="Activity appears as orders, collections and transfers are recorded."/>@endif
    </section>
  </div>
</div>
@endsection
