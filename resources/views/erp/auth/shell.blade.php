<!doctype html><html lang="en" data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title') · SL Distribution</title>
<script>(function(){try{var t=localStorage.getItem('erp-theme');if(!t)t=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
<link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}"><link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ @filemtime(public_path('css/admin.css')) }}"></head>
<body class="erp">
@include('erp.partials.icons')
<div class="auth-wrap">
  <aside class="auth-art">
    <div class="d-flex align-items-center gap-2"><span class="brand-mark">SL</span><b style="color:#fff">SL Distribution</b></div>
    <div>
      <h2>Stock, sales and field teams — one source of truth.</h2>
      <p>Warehouse-to-shop inventory with a tamper-proof ledger, credit control, and real-time collections from the field.</p>
      <ul>
        <li><x-icon name="check-circle"/>Ledger-backed stock across warehouses & distributors</li>
        <li><x-icon name="check-circle"/>Invoices, receivables aging and verified collections</li>
        <li><x-icon name="check-circle"/>Field staff attendance, orders and expenses</li>
      </ul>
    </div>
    <small style="color:#7f8fa6">© {{ date('Y') }} SL Distribution</small>
  </aside>
  <main class="auth-form"><div class="auth-card">
    <div class="d-flex align-items-center gap-2 d-lg-none"><span class="brand-mark">SL</span><b>SL Distribution</b></div>
    <h1>@yield('title')</h1>
    <p class="text-muted mb-4">@yield('lead', 'Admin · Distributor · Accounts · Inventory')</p>
    @if(session('status'))<div class="alert alert-success" role="status"><x-icon name="check-circle" size="sm"/><div>{{ session('status') }}</div></div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert"><x-icon name="alert" size="sm"/><div>{{ $errors->first() }}</div></div>@endif
    @yield('content')
  </div></main>
</div>
<script>document.querySelectorAll('form').forEach(f=>f.addEventListener('submit',()=>{const b=f.querySelector('button[type=submit]');if(b){b.disabled=true;b.textContent='Please wait…'}}));</script>
</body></html>
