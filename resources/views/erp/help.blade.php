@extends('erp.layout')
@section('title', 'Help Center')
@section('subtitle', 'Step-by-step guides, a complete worked example and answers to common questions.')
@section('content')
@php($u = auth()->user())
@php($pages = collect(\App\Support\Help::PAGES)->except(['erp.help', 'erp.shop', 'erp.invoice'])
    ->filter(fn ($p, $route) => collect(\App\Support\AdminNav::groups())->flatten(1)->contains(fn ($i) => $i[0] === $route && (! $i[3] || $u->hasRole(...$i[3])))))

<div class="search-input mb-3" style="max-width:560px">
  <x-icon name="search" size="sm"/>
  <input type="search" class="form-control" id="help-q" placeholder="Search help, e.g. &quot;damaged&quot;, &quot;verify&quot;, &quot;return&quot;" aria-label="Search help" autofocus>
</div>
<p class="text-muted small" id="help-none" hidden>No help topics match. Try a simpler word, or ask your administrator.</p>

<div class="row g-3">
  <div class="col-lg-5">
    <section class="panel mb-3 help-item" id="start">
      <div class="panel-head"><div><h2>Getting started as {{ \App\Support\Ui::label($u->role) }}</h2>
        <p class="hint">{{ \App\Support\Ui::ROLE_HELP[$u->role] ?? '' }}</p></div></div>
      <div class="panel-body">
        <ol class="mb-0 ps-3">
          @foreach($guide as [$title, $text, $route])
            <li class="mb-2"><b>@if($route && Route::has($route))<a href="{{ route($route) }}">{{ $title }}</a>@else{{ $title }}@endif</b><br><span class="text-muted small">{{ $text }}</span></li>
          @endforeach
        </ol>
      </div>
    </section>

    <section class="panel mb-3 help-item" id="words">
      <div class="panel-head"><h2>Words used in this system</h2></div>
      <div class="panel-body">
        <dl class="kv">
          <dt>On hand</dt><dd>All stock physically at a place.</dd>
          <dt>Reserved</dt><dd>Stock promised to open orders, not yet delivered.</dd>
          <dt>Available</dt><dd>On hand minus reserved. Only this can be sold.</dd>
          <dt>In transit</dt><dd>Dispatched from a warehouse, not yet received.</dd>
          <dt>Transfer</dt><dd>Moving stock from a warehouse to a distributor.</dd>
          <dt>Invoice</dt><dd>Bill to a shop, created automatically at delivery.</dd>
          <dt>Outstanding / owed</dt><dd>Invoice total minus payments and credit notes.</dd>
          <dt>Overdue</dt><dd>Still owed after the due date (issue date + payment terms).</dd>
          <dt>Collected</dt><dd>Payment taken by staff, not yet checked by a manager.</dd>
          <dt>Verified</dt><dd>A manager confirmed the money reached the cash box or bank.</dd>
          <dt>Credit note</dt><dd>Reduces what a shop owes, e.g. after a return.</dd>
          <dt>SKU</dt><dd>A short unique code for a product.</dd>
        </dl>
      </div>
    </section>
  </div>

  <div class="col-lg-7">
    <section class="panel mb-3 help-item" id="example">
      <div class="panel-head"><div><h2>Worked example: from 100 strips to a verified payment</h2>
        <p class="hint">Follow one product through the whole system. Numbers on the right show the stock or money after each step.</p></div></div>
      <div class="table-wrap"><table class="table mb-0">
        <thead><tr><th>#</th><th>Who</th><th>What they do</th><th>Result</th></tr></thead>
        <tbody>
        @foreach(\App\Support\Help::EXAMPLE as $i => [$who, $do, $why, $after])
          <tr><td class="text-muted">{{ $i + 1 }}</td><td><span class="tag">{{ $who }}</span></td>
            <td><b>{{ $do }}</b><br><span class="small text-muted">{{ $why }}</span></td><td class="small fw-semibold">{{ $after }}</td></tr>
        @endforeach
        </tbody>
      </table></div>
      <div class="panel-foot small text-muted">Field staff never hold their own stock: they sell from their distributor's stock, and each order can be assigned to a staff member for delivery.</div>
    </section>

    <section class="panel mb-3" id="faq">
      <div class="panel-head"><h2>Questions people often ask</h2></div>
      <div class="panel-body">
        @foreach($faq as [$q, $a])
          <details class="help-item mb-2"><summary class="fw-semibold" style="cursor:pointer">{{ $q }}</summary><p class="mt-1 mb-2 text-muted">{{ $a }}</p></details>
        @endforeach
      </div>
    </section>
  </div>
</div>

<h2 class="h6 fw-bold mt-2 mb-2">How each page works</h2>
<div class="row g-3">
  @foreach($pages as $route => $p)
    <div class="col-md-6 col-xl-4 help-item" id="page-{{ \Illuminate\Support\Str::slug($p['title']) }}">
      <div class="panel h-100"><div class="panel-head"><h3>{{ $p['title'] }}</h3><a href="{{ route($route) }}" class="small">Open <x-icon name="arrow-right" size="sm"/></a></div>
        <div class="panel-body small">
          <p class="mb-2">{{ $p['what'] }}</p>
          @if($p['steps'])<ol class="ps-3 mb-2">@foreach($p['steps'] as $s)<li>{{ $s }}</li>@endforeach</ol>@endif
          @isset($p['example'])<div class="callout"><div><b>Example:</b> {{ $p['example'] }}</div></div>@endisset
        </div></div>
    </div>
  @endforeach
</div>

<section class="panel mt-3 help-item" id="support">
  <div class="panel-body d-flex gap-3 align-items-start">
    <x-icon name="info"/>
    <div><b>Still stuck?</b> If something looks wrong, the red message on the screen says what to fix.
      @if($u->hasRole('admin', 'viewer')) The <a href="{{ route('erp.audit') }}">Audit Logs</a> shows who changed what and when.
      @else Contact your administrator @if($u->distributor) or {{ $u->distributor->business_name }} @endif and tell them the page name and the message you saw.@endif</div>
  </div>
</section>

<script>
(function(){var q=document.getElementById('help-q'),none=document.getElementById('help-none');
 q.addEventListener('input',function(){var t=q.value.trim().toLowerCase(),shown=0;
  document.querySelectorAll('.help-item').forEach(function(el){var hit=!t||el.textContent.toLowerCase().indexOf(t)>-1;el.hidden=!hit;if(hit){shown++;if(t&&el.tagName==='DETAILS')el.open=true;}});
  none.hidden=shown>0;});})();
</script>
@endsection
