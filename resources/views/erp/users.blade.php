@extends('erp.layout')
@use('App\Support\Fmt')
@use('App\Support\Ui')
@section('title', 'Users & Staff')
@section('crumb')<span>Administration</span>@endsection
@section('subtitle', 'Everyone who can sign in to the web panel or the field staff mobile app. Add people, set their mobile login and reset passwords.')
@php
  $admin = auth()->user()->hasRole('admin');
  $roles = $admin ? \App\Models\User::ROLES : ['staff'];
@endphp
@section('actions')
  <button type="button" class="btn btn-primary" data-open="add-user"><x-icon name="plus" size="sm"/>Add user</button>
@endsection
@section('content')
<section class="panel">
  <div class="toolbar">
    <div class="seg" id="role-seg">
      <a href="#" class="on" data-role="">All <span class="n">{{ $users->count() }}</span></a>
      @foreach($users->groupBy('role') as $r => $g)<a href="#" data-role="{{ Fmt::label($r) }}">{{ Fmt::label($r) }} <span class="n">{{ $g->count() }}</span></a>@endforeach
    </div>
    <div class="search-input grow"><x-icon name="search" size="sm"/><input class="form-control" placeholder="Search name or email…  ( / )" data-table-search="#users-table" aria-label="Search users"></div>
  </div>
  <div class="table-wrap"><table class="table table-hover" id="users-table">
    <thead><tr><th data-sort>User</th><th data-sort>Role</th><th data-sort>Distributor</th><th>Joined</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    @foreach($users as $x)
      <tr data-role="{{ Fmt::label($x->role) }}">
        <td><span class="d-flex align-items-center gap-2"><span class="avatar" style="background: {{ Fmt::avatarColor($x->name) }}">{{ Fmt::initials($x->name) }}</span><span><span class="cell-main">{{ $x->name }}@if($x->id === auth()->id()) <span class="tag">You</span>@endif</span><span class="cell-sub">{{ $x->email }}@if($x->phone) · {{ $x->phone }}@endif</span></span></span></td>
        <td><span class="tag" data-tip="{{ Fmt::label($x->role) }}|{{ Ui::ROLE_HELP[$x->role] ?? '' }}">{{ Fmt::label($x->role) }}</span>@if($x->role === 'staff')<span class="badge-s info ms-1"><span class="dot"></span>Mobile app</span>@endif</td>
        <td>{{ $x->distributor?->business_name ?? '—' }}</td>
        <td class="text-nowrap">{{ $x->created_at->format('d M Y') }}</td>
        <td><x-erp.status :value="$x->status"/></td>
        <td class="actions"><div class="d-inline-flex gap-1">@if(! $x->hasRole('admin') || $admin)<button type="button" class="btn btn-sm btn-light" data-open="ph-{{ $x->id }}"><x-icon name="user" size="sm"/>{{ $x->phone ? 'Mobile' : 'Set mobile' }}</button><button type="button" class="btn btn-sm btn-light" data-open="pw-{{ $x->id }}"><x-icon name="lock" size="sm"/>Change password</button>@endif
        @if($x->id !== auth()->id())
          <form method="post" action="{{ route('erp.users.status', $x) }}" data-confirm="{{ $x->status === 'active' ? 'Suspend '.$x->name.'? They are signed out everywhere, including the mobile app.' : 'Re-activate '.$x->name.'?' }}" data-confirm-ok="{{ $x->status === 'active' ? 'Suspend' : 'Activate' }}" @if($x->status === 'active') data-tone="danger" @endif>@csrf
            <input type="hidden" name="status" value="{{ $x->status === 'active' ? 'suspended' : 'active' }}">
            <button class="btn btn-sm {{ $x->status === 'active' ? 'btn-outline-danger' : 'btn-outline-success' }}"><x-icon :name="$x->status === 'active' ? 'pause' : 'play'" size="sm"/>{{ $x->status === 'active' ? 'Suspend' : 'Activate' }}</button></form>
        @endif</div></td>
      </tr>
    @endforeach
    <tr class="no-results"><td colspan="6"><x-erp.empty icon="search" title="No users match"/></td></tr>
    </tbody>
  </table></div>
</section>

@php
  $roleMeta = [
      'admin' => ['shield', '#e11d48', ['Web panel', 'All distributors']],
      'inventory_manager' => ['warehouse', '#7c3aed', ['Web panel', 'Stock & transfers']],
      'accounts_manager' => ['wallet', '#059669', ['Web panel', 'Payments & expenses']],
      'distributor' => ['building', '#d97706', ['Web panel', 'One distributor']],
      'staff' => ['pin', '#2563eb', ['Mobile app', 'Web panel']],
      'viewer' => ['eye', '#64748b', ['Web panel', 'Read-only']],
  ];
  $oldRole = old('_form') === 'add-user' ? old('role') : null;
  $selRole = in_array($oldRole, $roles, true) ? $oldRole : (in_array('staff', $roles, true) ? 'staff' : $roles[0]);
  $selLogin = old('_form') === 'add-user' ? old('login', 'password') : 'password';
  $firstRole = '#role-'.$roles[0];
@endphp
<x-erp.modal id="add-user" title="Add user" subtitle="Create a sign-in for the web panel — and for the mobile app if they are field staff." :action="route('erp.users.store')" submit="Create user" size="wide">
  {{-- Step 1: role --}}
  <div class="step-block">
    <div class="form-step"><span class="n">1</span><b>What will this person do?</b><span class="hint">Their role decides what they can see and change.</span></div>
    <div class="choice-grid" role="radiogroup" aria-label="Role">
      @foreach($roles as $r)
        @php [$ic, $col, $tags] = $roleMeta[$r] ?? ['user', '#3b82f6', ['Web panel']]; @endphp
        <label class="choice" style="--rc: {{ $col }}">
          <input type="radio" name="role" value="{{ $r }}" id="role-{{ $r }}" @checked($selRole === $r) required>
          <span class="card-in">
            <span class="r-ic"><x-icon :name="$ic"/></span>
            <span class="r-body"><b>{{ Fmt::label($r) }}</b><p>{{ Ui::ROLE_HELP[$r] ?? '' }}</p>
              <span class="r-tags">@foreach($tags as $t)<span class="{{ $t === 'Mobile app' ? 'app' : '' }}">{{ $t }}</span>@endforeach</span></span>
          </span>
          <span class="tick"><x-icon name="check"/></span>
        </label>
      @endforeach
    </div>
    @if(old('_form') === 'add-user')@error('role')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
    @if($admin)
      <div class="row g-3 mt-1" data-show-if="{{ $firstRole }}:{{ implode(',', Ui::DISTRIBUTOR_ROLES) }}">
        <x-erp.field form="add-user" class="col-md-7" name="distributor_id" label="Works for distributor" required id="au-dist" help="Distributors and field staff only see this distributor’s shops, orders and money.">
          <select id="au-dist" name="distributor_id" class="form-select" required><option value="">Select distributor…</option>@foreach($distributors as $d)<option value="{{ $d->id }}" @selected(old('distributor_id') == $d->id)>{{ $d->business_name }}{{ $d->area ? ' · '.$d->area : '' }}</option>@endforeach</select>
        </x-erp.field>
      </div>
    @endif
  </div>

  {{-- Step 2: person --}}
  <div class="step-block">
    <div class="form-step"><span class="n">2</span><b>Who is it?</b></div>
    <div class="row g-3">
      <x-erp.field form="add-user" class="col-md-6" name="name" label="Full name" required maxlength="255" placeholder="e.g. Ravi Kumar" autocomplete="off"/>
      <x-erp.field form="add-user" class="col-md-6" name="email" label="Email" type="email" required placeholder="name@company.com" autocomplete="off" help="Used for the web panel and password resets."/>
      <x-erp.field form="add-user" class="col-md-6" name="phone" label="Mobile number" type="tel" id="au-phone" maxlength="20" placeholder="98765 43210" inputmode="numeric" autocomplete="off"
        required :data-require-if="$firstRole.':staff'" help="Field staff sign in to the mobile app with this number + password."/>
    </div>
  </div>

  {{-- Step 3: sign-in --}}
  <div class="step-block">
    <div class="form-step"><span class="n">3</span><b>How will they sign in?</b></div>
    <div class="choice-grid two" role="radiogroup" aria-label="Sign-in method">
      <label class="choice" style="--rc:#4f46e5">
        <input type="radio" name="login" value="password" id="login-password" @checked($selLogin === 'password')>
        <span class="card-in"><span class="r-ic"><x-icon name="lock"/></span>
          <span class="r-body"><b>Set a password now</b><p>They can sign in straight away. You share the password with them.</p><span class="r-tags"><span>Recommended for field staff</span></span></span></span>
        <span class="tick"><x-icon name="check"/></span>
      </label>
      <label class="choice" style="--rc:#0d9488">
        <input type="radio" name="login" value="invite" @checked($selLogin === 'invite')>
        <span class="card-in"><span class="r-ic"><x-icon name="mail"/></span>
          <span class="r-body"><b>Email an invite</b><p>We email a secure link; they choose their own password.</p><span class="r-tags"><span>Needs a working email</span></span></span></span>
        <span class="tick"><x-icon name="check"/></span>
      </label>
    </div>
    <div class="row g-3 mt-1" data-show-if="#login-password:password">
      <div class="col-md-6">
        <label class="form-label" for="au-pw">Password<span class="req" aria-hidden="true">*</span></label>
        <div class="input-group">
          <input id="au-pw" name="password" type="password" class="form-control{{ old('_form') === 'add-user' && $errors->has('password') ? ' is-invalid' : '' }}" required minlength="8" maxlength="255" autocomplete="new-password" aria-describedby="au-pw-label">
          <button type="button" class="btn btn-light" data-pw-toggle="#au-pw" title="Show / hide"><x-icon name="eye" size="sm"/></button>
          <button type="button" class="btn btn-light" data-pw-generate title="Generate a strong password">Generate</button>
        </div>
        <div class="pw-meter" data-pw-meter="#au-pw" data-s="0"><i></i><i></i><i></i><i></i></div>
        <div class="pw-label" id="au-pw-label" data-pw-label>At least 8 characters. Mix letters, numbers and a symbol.</div>
        @if(old('_form') === 'add-user')@error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
      </div>
      <div class="col-md-6">
        <label class="form-label" for="au-pw2">Confirm password<span class="req" aria-hidden="true">*</span></label>
        <div class="input-group">
          <input id="au-pw2" name="password_confirmation" type="password" class="form-control" required minlength="8" maxlength="255" autocomplete="new-password">
          <button type="button" class="btn btn-light" data-pw-copy="#au-pw" title="Copy password to share"><x-icon name="file" size="sm"/>Copy</button>
        </div>
        <div class="pw-label" data-pw-match></div>
      </div>
    </div>
  </div>
</x-erp.modal>

@foreach($users as $x)
  @continue($x->hasRole('admin') && ! $admin)
  <x-erp.modal :id="'ph-'.$x->id" :title="'Mobile login · '.$x->name" subtitle="The number this person types with their password on the mobile app. Leave blank to remove." :action="route('erp.users.phone', $x)" submit="Save mobile" size="narrow">
    <x-erp.field :form="'ph-'.$x->id" name="phone" label="Mobile number" type="tel" maxlength="20" :value="$x->phone" placeholder="98765 43210"/>
  </x-erp.modal>
  <x-erp.modal :id="'pw-'.$x->id" :title="'Change password · '.$x->name" :subtitle="$x->email.' — they will be signed out of the web panel and mobile app.'"
    :action="route('erp.users.password', $x)" submit="Change password" size="narrow">
    <div class="row g-3">
      <x-erp.field :form="'pw-'.$x->id" class="col-12" name="password" label="New password" type="password" required minlength="8" autocomplete="new-password" help="At least 8 characters. Share it with them securely."/>
      <x-erp.field :form="'pw-'.$x->id" class="col-12" name="password_confirmation" label="Confirm new password" type="password" required minlength="8" autocomplete="new-password"/>
    </div>
  </x-erp.modal>
@endforeach
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Role filter chips (client-side).
  document.querySelectorAll('#role-seg a').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      document.querySelectorAll('#role-seg a').forEach(function (x) { x.classList.toggle('on', x === a); });
      document.querySelectorAll('#users-table tbody tr[data-role]').forEach(function (tr) { tr.hidden = a.dataset.role && tr.dataset.role !== a.dataset.role; });
    });
  });
  // Password tools: show/hide, generate, copy, strength meter, match hint.
  var pw = document.getElementById('au-pw'), pw2 = document.getElementById('au-pw2');
  if (!pw) return;
  var meter = document.querySelector('[data-pw-meter]'), label = document.querySelector('[data-pw-label]'), match = document.querySelector('[data-pw-match]');
  var words = ['Too short', 'Weak', 'Fair', 'Good', 'Strong'];
  var score = function (v) {
    if (v.length < 8) return v.length ? 1 : 0;
    var s = 1; if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++; if (/\d/.test(v)) s++; if (/[^A-Za-z0-9]/.test(v) || v.length >= 14) s++;
    return Math.min(4, s);
  };
  var refresh = function () {
    var s = score(pw.value); meter.dataset.s = s;
    label.textContent = pw.value ? 'Strength: ' + words[s] + (pw.value.length < 8 ? ' — use at least 8 characters' : '') : 'At least 8 characters. Mix letters, numbers and a symbol.';
    match.textContent = pw2.value ? (pw2.value === pw.value ? '✓ Passwords match' : '✗ Passwords do not match') : '';
    match.style.color = pw2.value && pw2.value !== pw.value ? 'var(--bad)' : 'var(--ok)';
    pw2.setCustomValidity(pw2.value && pw2.value !== pw.value ? 'Passwords do not match' : '');
  };
  pw.addEventListener('input', refresh); pw2.addEventListener('input', refresh);
  document.querySelectorAll('[data-pw-toggle]').forEach(function (b) {
    b.addEventListener('click', function () { var t = pw.type === 'password' ? 'text' : 'password'; pw.type = t; pw2.type = t; });
  });
  document.querySelector('[data-pw-generate]').addEventListener('click', function () {
    var a = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789', sym = '@#$%&*!', r = new Uint32Array(12), out = '';
    crypto.getRandomValues(r);
    for (var i = 0; i < 10; i++) out += a[r[i] % a.length];
    out += sym[r[10] % sym.length] + (r[11] % 10);
    pw.value = pw2.value = out; pw.type = pw2.type = 'text'; refresh();
    window.erpToast && window.erpToast('Strong password generated — copy it to share with the user.');
  });
  document.querySelector('[data-pw-copy]').addEventListener('click', function () {
    if (!pw.value) return;
    (navigator.clipboard ? navigator.clipboard.writeText(pw.value) : Promise.reject()).then(function () { window.erpToast && window.erpToast('Password copied.'); }, function () { pw.type = 'text'; pw.select(); });
  });
  refresh();
});
</script>
@endpush
@endsection
