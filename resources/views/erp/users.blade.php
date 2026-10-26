@extends('erp.layout')
@use('App\Support\Fmt')
@use('App\Support\Ui')
@section('title', 'Users & Staff')
@section('crumb')<span>Administration</span>@endsection
@section('subtitle', 'People who can sign in. New users receive an activation email and set their own password.')
@php($admin = auth()->user()->hasRole('admin'))
@php($roles = $admin ? \App\Models\User::ROLES : ['staff'])
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

<x-erp.modal id="add-user" title="Add user" subtitle="Create a login for the web panel (and the mobile app for field staff)." :action="route('erp.users.store')" submit="Create user">
  <div class="row g-3">
    <x-erp.field form="add-user" class="col-md-6" name="name" label="Full name" required maxlength="255"/>
    <x-erp.field form="add-user" class="col-md-6" name="email" label="Email" type="email" required/>
    <x-erp.field form="add-user" class="col-md-6" name="phone" label="Mobile number" type="tel" maxlength="20" placeholder="98765 43210" help="Field staff sign in to the mobile app with this number + password"/>
    <div class="col-12">
      <span class="form-label d-block">Role<span class="req" aria-hidden="true">*</span></span>
      <div class="gap-grid" style="grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:.5rem">
        @foreach($roles as $r)
          <label class="qty-item" style="align-items:flex-start"><input class="form-check-input mt-1" type="radio" name="role" value="{{ $r }}" id="role-{{ $r }}" @checked((old('_form') === 'add-user' ? old('role') : null) === $r || (! old('role') && $r === 'staff')) required>
            <span class="info"><b>{{ Fmt::label($r) }}</b><span style="white-space:normal">{{ Ui::ROLE_HELP[$r] ?? '' }}</span></span></label>
        @endforeach
      </div>
      @if(old('_form') === 'add-user')@error('role')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
    </div>
    @if($admin)
      <input type="hidden" id="role-current" value="">
      <x-erp.field form="add-user" class="col-12" name="distributor_id" label="Distributor" required id="au-dist" data-show-if="#role-current:{{ implode(',', Ui::DISTRIBUTOR_ROLES) }}">
        <select id="au-dist" name="distributor_id" class="form-select"><option value="">Select distributor…</option>@foreach($distributors as $d)<option value="{{ $d->id }}" @selected(old('distributor_id') == $d->id)>{{ $d->business_name }}</option>@endforeach</select>
      </x-erp.field>
    @endif
    <div class="col-12">
      <span class="form-label d-block">Login</span>
      <input type="hidden" name="login" value="invite">
      <label class="qty-item" style="align-items:flex-start"><input class="form-check-input mt-1" type="checkbox" name="login" value="password" id="au-setpw" @checked(old('_form') === 'add-user' ? old('login') === 'password' : true)>
        <span class="info"><b>Set email &amp; password now</b><span style="white-space:normal">They can sign in immediately with this email and password — field staff on the mobile app too. Untick to email them an activation link instead.</span></span></label>
    </div>
    <div class="col-12" data-show-if="#au-setpw:checked">
      <div class="row g-3">
        <x-erp.field form="add-user" class="col-md-6" name="password" label="Password" type="password" required minlength="8" autocomplete="new-password" help="At least 8 characters"/>
        <x-erp.field form="add-user" class="col-md-6" name="password_confirmation" label="Confirm password" type="password" required minlength="8" autocomplete="new-password"/>
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
  // Mirror the chosen role into a hidden control so data-show-if can toggle the distributor field.
  var cur = document.getElementById('role-current');
  if (cur) {
    var sync = function () { var r = document.querySelector('input[name=role]:checked'); cur.value = r ? r.value : ''; cur.dispatchEvent(new Event('change', { bubbles: true })); };
    document.querySelectorAll('input[name=role]').forEach(function (r) { r.addEventListener('change', sync); });
    sync();
  }
});
</script>
@endpush
@endsection
