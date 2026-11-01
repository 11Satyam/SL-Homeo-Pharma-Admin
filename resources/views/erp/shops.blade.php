@extends('erp.layout')
@use('App\Support\Fmt')
@section('title', 'Shops')
@section('crumb')<span>Sales</span>@endsection
@section('subtitle', 'Retail chemists and medical stores served by each distributor, with credit exposure at a glance.')
@php($u = auth()->user())
@php($canAdd = $u->hasRole('admin', 'distributor'))
@php($isAdmin = $u->hasRole('admin'))
@php($shopDistributors = $shops->pluck('distributor')->filter()->unique('id')->sortBy('business_name'))
@section('actions')
  @if($canAdd)<button type="button" class="btn btn-primary" data-open="add-shop"><x-icon name="plus" size="sm"/>Add shop</button>@endif
@endsection
@section('content')
<div class="row g-3 mb-3">
  <div class="col-sm-4"><x-erp.stat mini label="Active shops" :value="$shops->where('status', 'active')->count()" icon="store"/></div>
  <div class="col-sm-4"><x-erp.stat mini label="Total outstanding" :value="Fmt::money($shops->sum('outstanding'), 0)" icon="wallet" tone="warn"/></div>
  <div class="col-sm-4"><x-erp.stat mini label="Over credit limit" :value="$shops->filter(fn ($s) => $s->credit_limit > 0 && $s->outstanding > $s->credit_limit)->count()" icon="alert" tone="bad"/></div>
</div>
<section class="panel">
  <div class="toolbar">
    <div class="search-input grow"><x-icon name="search" size="sm"/><input class="form-control" placeholder="Search shop, owner, phone or area…  ( / )" data-table-search="#shops" aria-label="Search shops"></div>
    <span class="text-muted small">{{ $shops->count() }} shops</span>
  </div>
  @if($shops->count())
  <div class="table-wrap tall"><table class="table table-hover" id="shops">
    <thead><tr><th data-sort>Shop</th><th>Contact</th><th data-sort>Area</th>@unless($u->scopedDistributorId())<th data-sort>Distributor</th>@endunless<th class="text-end" data-sort="num">Outstanding</th><th style="width:150px">Credit used</th><th data-sort>Last order</th><th>Status</th>@if($canAdd)<th><span class="sr-only">Actions</span></th>@endif</tr></thead>
    <tbody>
    @foreach($shops as $s)
      @php($used = $s->credit_limit > 0 ? $s->outstanding / $s->credit_limit * 100 : 0)
      <tr>
        <td><a href="{{ route('erp.shop', $s) }}" class="cell-main">{{ $s->name }}</a><span class="cell-sub">{{ $s->invoices_count }} invoices · {{ $s->payment_terms_days }}-day terms</span></td>
        <td>{{ $s->owner ?: '—' }}<span class="cell-sub">{{ $s->phone }}</span></td>
        <td>{{ $s->area ?: '—' }}</td>
        @unless($u->scopedDistributorId())
          <td>{{ $s->distributor->business_name }}@if($isAdmin)<span class="cell-sub"><a href="#" data-open="edit-dist-{{ $s->distributor_id }}"><x-icon name="edit" size="sm"/> Edit distributor</a></span>@endif</td>
        @endunless
        <td class="text-end num fw-semibold" data-v="{{ $s->outstanding }}">{{ Fmt::money($s->outstanding, 0) }}</td>
        <td>@if($s->credit_limit > 0)<div class="meter {{ $used > 100 ? 'bad' : ($used > 75 ? 'warn' : '') }}" data-tip="Credit used|{{ Fmt::money($s->outstanding, 0) }} of {{ Fmt::money($s->credit_limit, 0) }} ({{ round($used) }}%)"><span style="width: {{ min(100, $used) }}%"></span></div><span class="cell-sub">{{ round($used) }}% of {{ Fmt::moneyShort($s->credit_limit) }}</span>@else<span class="text-muted small">No limit set</span>@endif</td>
        <td data-v="{{ $s->last_order_at }}">{{ $s->last_order_at ? \Carbon\Carbon::parse($s->last_order_at)->diffForHumans() : 'Never' }}</td>
        <td><x-erp.status :value="$s->status"/></td>
        @if($canAdd)<td class="actions"><button type="button" class="btn btn-sm btn-light" data-open="edit-shop-{{ $s->id }}"><x-icon name="edit" size="sm"/>Edit</button></td>@endif
      </tr>
    @endforeach
    <tr class="no-results"><td colspan="9"><x-erp.empty icon="search" title="No shops match your search"/></td></tr>
    </tbody>
  </table></div>
  @else
    <x-erp.empty icon="store" title="No shops yet" text="Add the retail shops your distributors supply to start taking orders.">
      @if($canAdd)<button type="button" class="btn btn-primary" data-open="add-shop"><x-icon name="plus" size="sm"/>Add shop</button>@endif
    </x-erp.empty>
  @endif
</section>

@if($canAdd)
  <x-erp.modal id="add-shop" title="Add retail shop" subtitle="Shops belong to one distributor; their orders and collections are scoped to it." :action="route('erp.shops.store')" submit="Add shop">
    <div class="row g-3">
      <x-erp.field form="add-shop" class="col-md-7" name="name" label="Shop name" required maxlength="255" placeholder="Shree Ganesh Medicals"/>
      <x-erp.field form="add-shop" class="col-md-5" name="area" label="Area / locality" maxlength="100" placeholder="Andheri West"/>
      <x-erp.field form="add-shop" class="col-md-6" name="owner" label="Owner / pharmacist" maxlength="255"/>
      <x-erp.field form="add-shop" class="col-md-6" name="phone" label="Phone" type="tel" maxlength="30" placeholder="98XXXXXXXX"/>
      @if($isAdmin)
        <x-erp.field form="add-shop" class="col-12" name="distributor_id" label="Distributor" required id="as-dist">
          <select id="as-dist" name="distributor_id" class="form-select" required><option value="">Select distributor…</option>@foreach($distributors as $d)<option value="{{ $d->id }}" @selected(old('distributor_id') == $d->id)>{{ $d->business_name }}{{ $d->area ? ' · '.$d->area : '' }}</option>@endforeach</select>
        </x-erp.field>
      @endif
      <x-erp.field form="add-shop" class="col-12" name="address" label="Address" maxlength="500"/>
      <x-erp.field form="add-shop" class="col-md-6" name="credit_limit" label="Credit limit (₹)" type="number" step="0.01" min="0" placeholder="50000"/>
      <x-erp.field form="add-shop" class="col-md-6" name="payment_terms_days" label="Payment terms (days)" type="number" min="0" max="365" value="15" help="Due date = invoice date + terms"/>
    </div>
  </x-erp.modal>

  {{-- Edit shop --}}
  @foreach($shops as $s)
    @php($fid = 'edit-shop-'.$s->id)
    @php($locked = $s->invoices_count > 0 || $s->last_order_at)
    <x-erp.modal :id="$fid" :title="'Edit shop · '.$s->name" :subtitle="$s->distributor->business_name.' · '.($s->area ?: 'no area')" :action="route('erp.shops.update', $s)" submit="Save changes">
      <div class="row g-3">
        <x-erp.field :form="$fid" class="col-md-7" name="name" label="Shop name" required maxlength="255" :value="$s->name"/>
        <x-erp.field :form="$fid" class="col-md-5" name="area" label="Area / locality" maxlength="100" :value="$s->area"/>
        <x-erp.field :form="$fid" class="col-md-6" name="owner" label="Owner / pharmacist" maxlength="255" :value="$s->owner"/>
        <x-erp.field :form="$fid" class="col-md-6" name="phone" label="Phone" type="tel" maxlength="30" :value="$s->phone"/>
        @if($isAdmin)
          <x-erp.field :form="$fid" class="col-12" name="distributor_id" label="Distributor" :id="$fid.'-dist'"
            :help="$locked ? 'Locked: this shop already has orders or invoices with its distributor.' : 'Moving a shop changes who supplies it and whose reports it appears in.'">
            <select id="{{ $fid }}-dist" name="distributor_id" class="form-select" @disabled($locked)>
              @foreach($distributors->concat([$s->distributor])->unique('id')->sortBy('business_name') as $d)
                <option value="{{ $d->id }}" @selected((old('_form') === $fid ? old('distributor_id') : $s->distributor_id) == $d->id)>{{ $d->business_name }}{{ $d->area ? ' · '.$d->area : '' }}</option>
              @endforeach
            </select>
          </x-erp.field>
        @endif
        <x-erp.field :form="$fid" class="col-12" name="address" label="Address" maxlength="500" :value="$s->address"/>
        <x-erp.field :form="$fid" class="col-md-4" name="credit_limit" label="Credit limit (₹)" type="number" step="0.01" min="0" :value="$s->credit_limit"/>
        <x-erp.field :form="$fid" class="col-md-4" name="payment_terms_days" label="Payment terms (days)" type="number" min="0" max="365" required :value="$s->payment_terms_days" help="Applies to future invoices"/>
        <x-erp.field :form="$fid" class="col-md-4" name="status" label="Status" required :id="$fid.'-status'" help="Inactive shops can’t receive new orders">
          <select id="{{ $fid }}-status" name="status" class="form-select">
            @foreach(['active' => 'Active', 'inactive' => 'Inactive'] as $k => $l)<option value="{{ $k }}" @selected((old('_form') === $fid ? old('status') : $s->status) === $k)>{{ $l }}</option>@endforeach
          </select>
        </x-erp.field>
      </div>
    </x-erp.modal>
  @endforeach

  {{-- Edit distributor (admin) --}}
  @if($isAdmin)
    @foreach($shopDistributors as $d)
      @include('erp.partials.distributor-edit', ['d' => $d])
    @endforeach
  @endif
@endif
@endsection
