@extends('erp.layout')
@use('App\Support\Fmt')
@section('title', 'Distributors')
@section('crumb')<span>Administration</span>@endsection
@section('subtitle', 'Each distributor gets a login, its own stock location and isolated shops, staff and finances.')
@section('actions')
  <a href="{{ route('erp.distributors.report') }}" class="btn btn-light"><x-icon name="ledger" size="sm"/>Stock report</a>
  <button type="button" class="btn btn-primary" data-open="add-dist"><x-icon name="plus" size="sm"/>Register distributor</button>
@endsection
@section('content')
<section class="panel">
  <div class="toolbar">
    <div class="search-input grow"><x-icon name="search" size="sm"/><input class="form-control" placeholder="Search distributors…  ( / )" data-table-search="#dist-table" aria-label="Search distributors"></div>
    <span class="text-muted small">{{ $distributors->where('status', 'active')->count() }} active of {{ $distributors->count() }}</span>
  </div>
  @if($distributors->count())
  <div class="table-wrap"><table class="table table-hover" id="dist-table">
    <thead><tr><th data-sort>Distributor</th><th>Contact</th><th data-sort>Territory</th><th class="text-end" data-sort="num">Shops</th><th class="text-end" data-sort="num">Users</th><th class="text-end">Credit limit</th><th>Geofence</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    @foreach($distributors as $d)
      <tr>
        <td><span class="d-flex align-items-center gap-2"><span class="avatar" style="background: {{ Fmt::avatarColor($d->business_name) }};border-radius:8px">{{ Fmt::initials($d->business_name) }}</span><span><a href="{{ route('erp.distributors.show', $d) }}" class="cell-main">{{ $d->business_name }}</a><span class="cell-sub">Since {{ $d->created_at->format('M Y') }}</span></span></span></td>
        <td>{{ $d->contact_person ?: '—' }}<span class="cell-sub">{{ $d->phone }} {{ $d->email }}</span></td>
        <td>{{ $d->area ?: '—' }}</td>
        <td class="text-end num">{{ $d->shops_count }}</td>
        <td class="text-end num">{{ $d->users->count() }}<span class="cell-sub">{{ $d->users->where('role', 'staff')->count() }} staff</span></td>
        <td class="text-end num">{{ $d->credit_limit > 0 ? Fmt::money($d->credit_limit, 0) : '—' }}</td>
        <td>@if($d->hasGeofence())<span class="tag" data-tip="Office geofence|{{ $d->latitude }}, {{ $d->longitude }} · {{ $d->geofence_radius_m }} m radius"><x-icon name="pin" size="sm"/>{{ $d->geofence_radius_m }} m</span>@else<span class="text-muted small">Not set</span>@endif</td>
        <td><x-erp.status :value="$d->status"/></td>
        <td class="actions">
          <details class="menu"><summary class="btn btn-sm btn-ghost" aria-label="Actions for {{ $d->business_name }}"><x-icon name="more"/></summary>
            <div class="menu-pop">
              <a class="mi" href="{{ route('erp.distributors.show', $d) }}"><x-icon name="eye" size="sm"/>Details &amp; stock</a>
              <button type="button" class="mi" data-open="edit-dist-{{ $d->id }}"><x-icon name="edit" size="sm"/>Edit details…</button>
              <button type="button" class="mi" data-open="geo-{{ $d->id }}"><x-icon name="pin" size="sm"/>Attendance geofence…</button>
              <a class="mi" href="{{ route('erp.shops') }}"><x-icon name="store" size="sm"/>View shops</a>
              <div class="sep"></div>
              <form method="post" action="{{ route('erp.distributors.status', $d) }}" data-confirm="{{ $d->status === 'active' ? 'Deactivate '.$d->business_name.'? All of its users are locked out immediately (history is kept).' : 'Re-activate '.$d->business_name.' and its users?' }}" data-confirm-ok="{{ $d->status === 'active' ? 'Deactivate' : 'Activate' }}" @if($d->status === 'active') data-tone="danger" @endif>@csrf
                <input type="hidden" name="status" value="{{ $d->status === 'active' ? 'inactive' : 'active' }}">
                <button class="mi {{ $d->status === 'active' ? 'danger' : '' }}"><x-icon :name="$d->status === 'active' ? 'pause' : 'play'" size="sm"/>{{ $d->status === 'active' ? 'Deactivate' : 'Activate' }}</button></form>
            </div></details>
        </td>
      </tr>
    @endforeach
    <tr class="no-results"><td colspan="9"><x-erp.empty icon="search" title="No distributors match"/></td></tr>
    </tbody>
  </table></div>
  @else
    <x-erp.empty icon="building" title="No distributors yet" text="Register your first distributor to start transferring stock and onboarding shops.">
      <button type="button" class="btn btn-primary" data-open="add-dist"><x-icon name="plus" size="sm"/>Register distributor</button>
    </x-erp.empty>
  @endif
</section>

@foreach($distributors as $d)
  @include('erp.partials.distributor-edit', ['d' => $d])
  <x-erp.modal :id="'geo-'.$d->id" :title="'Attendance geofence · '.$d->business_name" subtitle="Field staff check-ins are compared with this circle. Leave all fields blank to disable." :action="route('erp.distributors.geofence', $d)" submit="Save geofence">
    <div class="row g-3">
      <x-erp.field :form="'geo-'.$d->id" class="col-sm-4" name="latitude" label="Latitude" type="number" step="0.0000001" min="-90" max="90" :value="$d->latitude" placeholder="19.1197"/>
      <x-erp.field :form="'geo-'.$d->id" class="col-sm-4" name="longitude" label="Longitude" type="number" step="0.0000001" min="-180" max="180" :value="$d->longitude" placeholder="72.8464"/>
      <x-erp.field :form="'geo-'.$d->id" class="col-sm-4" name="geofence_radius_m" label="Radius (m)" type="number" min="20" max="50000" :value="$d->geofence_radius_m" placeholder="300"/>
    </div>
    <div class="callout mt-3"><x-icon name="info" size="sm"/>Tip: in Google Maps, right-click the office and copy the coordinates. GPS on phones is typically accurate to 10–50 m, so use at least 150 m.</div>
  </x-erp.modal>
@endforeach

<x-erp.modal id="add-dist" title="Register distributor" subtitle="Creates the distributor, its stock location and an owner login. An activation link is emailed — you never set their password." :action="route('erp.distributors.store')" submit="Register distributor">
  <div class="row g-3">
    <x-erp.field form="add-dist" class="col-md-7" name="business_name" label="Business name" required maxlength="255" placeholder="Shree Sai Pharma Distributors"/>
    <x-erp.field form="add-dist" class="col-md-5" name="area" label="Territory" maxlength="100" placeholder="Western suburbs"/>
    <x-erp.field form="add-dist" class="col-md-6" name="contact_person" label="Contact person" maxlength="255"/>
    <x-erp.field form="add-dist" class="col-md-6" name="phone" label="Phone" type="tel" maxlength="30"/>
    <x-erp.field form="add-dist" class="col-md-7" name="email" label="Login email" type="email" required help="The activation link is sent here."/>
    <x-erp.field form="add-dist" class="col-md-5" name="credit_limit" label="Credit limit (₹)" type="number" step="0.01" min="0"/>
    <x-erp.field form="add-dist" class="col-12" name="address" label="Address" maxlength="500"/>
  </div>
</x-erp.modal>
@endsection
