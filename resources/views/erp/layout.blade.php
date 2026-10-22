<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>@yield('title', 'Dashboard') · SL Distribution</title>
<script>(function(){try{var t=localStorage.getItem('erp-theme');if(!t)t=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
<link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ @filemtime(public_path('css/admin.css')) }}">
@stack('head')
</head>
<body class="erp">
@include('erp.partials.icons')
<div class="progress-top" aria-hidden="true"></div>
<a href="#main" class="sr-only">Skip to content</a>
@php($u = auth()->user())
@php($navAlerts = $navAlerts ?? [])
@php($alertTotal = collect($navAlerts)->where('count', '>', 0)->filter(fn ($a) => $a['hot'] || $a['tone'] !== 'info')->sum('count'))
<div class="shell">
  <aside class="sidebar" aria-label="Main navigation">
    <div class="brand">
      <div class="brand-mark" aria-hidden="true">SL</div>
      <div><div class="brand-name">SL Distribution</div><div class="brand-sub">{{ $u->distributor?->business_name ?? 'Head office' }}</div></div>
    </div>
    <nav class="side-nav">
      @foreach(\App\Support\AdminNav::groups() as $group => $items)
        @php($visible = array_filter($items, fn ($i) => ! $i[3] || $u->hasRole(...$i[3])))
        @if($visible)
          <div class="side-group">{{ $group }}</div>
          @foreach($visible as [$r, $label, $icon])
            @if(request()->routeIs($r) || request()->routeIs($r.'.*') || ($r === 'erp.shops' && request()->routeIs('erp.shop')) || ($r === 'erp.invoices' && request()->routeIs('erp.invoice')))
              @php($pageIcon = $icon) @php($pageGroup = $group)
            @endif
            @php($c = $navAlerts[$r] ?? null)
            <a href="{{ route($r) }}" class="grp-{{ Str::slug($group) }} {{ request()->routeIs($r) || request()->routeIs($r.'.*') || ($r === 'erp.shops' && request()->routeIs('erp.shop')) || ($r === 'erp.invoices' && request()->routeIs('erp.invoice')) ? 'active' : '' }}"
               @if(request()->routeIs($r)) aria-current="page" @endif>
              <x-icon :name="$icon"/><span>{{ $label }}</span>
              @if($c && $c['count'] > 0)<span class="count {{ $c['hot'] ? 'hot' : '' }}" title="{{ $c['title'] }}">{{ $c['count'] > 99 ? '99+' : $c['count'] }}</span>@endif
            </a>
          @endforeach
        @endif
      @endforeach
      @if(Route::has('erp.help'))
        <div class="side-group">Support</div>
        <a href="{{ route('erp.help') }}" class="{{ request()->routeIs('erp.help') ? 'active' : '' }}"><x-icon name="info"/><span>Help center</span></a>
      @endif
    </nav>
    <div class="side-foot">
      <span class="avatar" style="background: {{ \App\Support\Fmt::avatarColor($u->name) }}">{{ \App\Support\Fmt::initials($u->name) }}</span>
      <div class="who"><b>{{ $u->name }}</b><span>{{ \App\Support\Fmt::label($u->role) }}</span></div>
      <form method="post" action="{{ route('logout') }}">@csrf<button class="icon-btn" style="color:#a9b6c8" title="Log out" aria-label="Log out"><x-icon name="logout"/></button></form>
    </div>
  </aside>
  <div class="scrim" aria-hidden="true"></div>

  <div class="main">
    <header class="topbar">
      <button type="button" class="icon-btn menu-toggle" data-nav-toggle aria-label="Open navigation" aria-expanded="false"><x-icon name="menu"/></button>
      <nav class="crumbs" aria-label="Breadcrumb">
        <a href="{{ route('erp.dashboard') }}" class="hide-xs">Home</a>
        @hasSection('crumb')<x-icon name="chev-right" size="sm" class="hide-xs"/>@yield('crumb')@endif
        <x-icon name="chev-right" size="sm" class="hide-xs"/><span class="cur">@yield('title', 'Dashboard')</span>
      </nav>
      <div class="tools">
        @if(Route::has('erp.help'))<a href="{{ route('erp.help') }}" class="icon-btn" title="Help center" aria-label="Help center"><x-icon name="info"/></a>@endif
        <button type="button" class="icon-btn" data-theme-toggle aria-label="Toggle dark mode"><x-icon name="moon" class="when-light"/><x-icon name="sun" class="when-dark"/></button>
        <details class="menu">
          <summary class="icon-btn" aria-label="Notifications" title="Notifications"><x-icon name="bell"/>@if($alertTotal)<span class="pip">{{ $alertTotal > 99 ? '99+' : $alertTotal }}</span>@endif</summary>
          <div class="menu-pop notif-pop">
            <div class="nh"><span>Needs attention</span><span class="text-muted small">{{ now()->format('d M, H:i') }}</span></div>
            @php($items = collect($navAlerts)->where('count', '>', 0))
            @forelse($items as $route => $a)
              <a class="notif-item" href="{{ route($route) }}"><span class="ni a-ic {{ $a['tone'] }}"><x-icon :name="$a['icon']" size="sm"/></span><div><b>{{ $a['title'] }}</b><span>{{ $a['text'] }}</span></div></a>
            @empty
              <x-erp.empty icon="check-circle" title="All caught up" text="Nothing needs your attention right now." style="padding:1.75rem 1rem"/>
            @endforelse
          </div>
        </details>
        <details class="menu">
          <summary class="icon-btn" aria-label="Account menu" style="width:auto;padding:0 .35rem;gap:.4rem;display:inline-flex">
            <span class="avatar sm" style="background: {{ \App\Support\Fmt::avatarColor($u->name) }}">{{ \App\Support\Fmt::initials($u->name) }}</span><x-icon name="chev-down" size="sm" class="hide-xs"/>
          </summary>
          <div class="menu-pop">
            <div class="mh">{{ $u->name }}</div>
            <div class="px-2 pb-2 small text-muted" style="padding-left:.65rem!important">{{ $u->email }}<br>{{ \App\Support\Fmt::label($u->role) }}@if($u->distributor) · {{ $u->distributor->business_name }}@endif</div>
            <div class="sep"></div>
            <button type="button" class="mi" data-theme-toggle><x-icon name="sun" size="sm"/>Toggle light / dark</button>
            <div class="sep"></div>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="mi danger"><x-icon name="logout" size="sm"/>Log out</button></form>
          </div>
        </details>
      </div>
    </header>

    <main class="content" id="main" tabindex="-1">
      <div class="page">
        <div class="page-head">
          <div class="d-flex align-items-center gap-3">@isset($pageIcon)<span class="ph-icon grp-{{ Str::slug($pageGroup) }}" aria-hidden="true"><x-icon :name="$pageIcon"/></span>@endisset<div><h1>@yield('title', 'Dashboard')</h1>@hasSection('subtitle')<p class="sub">@yield('subtitle')</p>@endif</div></div>
          @hasSection('actions')<div class="actions">@yield('actions')</div>@endif
        </div>
        @if(session('status') && session('next'))
          <div class="alert alert-success mb-3" role="status"><x-icon name="check-circle" size="sm"/>
            <div class="flex-fill"><div>{{ session('status') }}</div>
              <div class="chip-row mt-2">@foreach((array) session('next') as [$nl, $nu])<a href="{{ $nu }}" class="btn btn-sm btn-light">{{ $nl }}<x-icon name="arrow-right" size="sm"/></a>@endforeach</div></div></div>
        @endif
        @if($errors->any() && ! old('_form'))
          <div class="alert alert-danger mb-3" role="alert"><x-icon name="alert" size="sm"/><div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div></div>
        @endif
        @yield('content')
      </div>
      <div class="page-skeleton" aria-hidden="true">
        <div class="page-head"><div><span class="skel" style="width:220px;height:26px"></span><span class="skel mt-2" style="width:320px;height:14px"></span></div></div>
        <div class="row g-3 mb-3">@for($i = 0; $i < 4; $i++)<div class="col-6 col-xl-3"><div class="stat"><span class="skel" style="width:60%;height:14px"></span><span class="skel mt-3" style="width:45%;height:26px"></span><span class="skel mt-2" style="width:70%;height:12px"></span></div></div>@endfor</div>
        <div class="panel"><div class="panel-head"><span class="skel" style="width:180px;height:16px"></span></div>
          <div class="panel-body">@for($i = 0; $i < 6; $i++)<div class="d-flex gap-3 mb-3"><span class="skel" style="width:22%;height:14px"></span><span class="skel" style="width:30%;height:14px"></span><span class="skel" style="width:18%;height:14px"></span><span class="skel" style="width:12%;height:14px"></span></div>@endfor</div></div>
      </div>
    </main>
    <footer class="app-foot"><span>© {{ date('Y') }} SL Distribution · Stock & Distribution Management</span><span>Signed in as {{ \App\Support\Fmt::label($u->role) }}</span></footer>
  </div>
</div>

<div class="toasts" aria-live="polite">
  @if(session('status'))<span hidden data-toast="{{ session('status') }}" data-kind="success"></span>@endif
  @if($errors->any())<span hidden data-toast="{{ $errors->first() }}" data-kind="error"></span>@endif
</div>

<dialog id="confirm-dlg" class="modal-x narrow" aria-labelledby="confirm-t">
  <div class="dlg-inner">
    <div class="dlg-body" style="padding-top:1.5rem">
      <div class="confirm-ic"><x-icon name="alert"/></div>
      <h2 id="confirm-t" class="h6 fw-bold mb-1" data-c-title>Please confirm</h2>
      <p class="text-2 mb-0" data-c-text></p>
    </div>
    <div class="dlg-foot"><button type="button" class="btn btn-light" data-close>Cancel</button><button type="button" class="btn btn-primary" data-c-ok>Confirm</button></div>
  </div>
</dialog>

<script src="{{ asset('js/admin.js') }}?v={{ @filemtime(public_path('js/admin.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
