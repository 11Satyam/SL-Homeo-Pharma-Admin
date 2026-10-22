@extends('erp.layout')
@use('App\Support\Fmt')
@section('title', 'Stock Transfers')
@section('crumb')<span>Inventory</span>@endsection
@section('subtitle', 'Draft → Approved → In transit → Received. Stock leaves the source only on dispatch and lands at the destination only on receipt.')
@php($u = auth()->user())
@php($canCreate = $u->hasRole('admin', 'inventory_manager'))
@php($flow = ['draft', 'approved', 'in_transit', 'received'])
@section('actions')
  @if($canCreate)<button type="button" class="btn btn-primary" data-open="new-transfer"><x-icon name="plus" size="sm"/>New transfer</button>@endif
@endsection
@section('content')
<section class="panel">
  <div class="toolbar">
    <div class="seg">
      <a href="{{ route('erp.transfers') }}" class="{{ request('status') ? '' : 'on' }}">All <span class="n">{{ $statusCounts->sum() }}</span></a>
      @foreach(['draft', 'approved', 'in_transit', 'partially_received', 'received'] as $s)
        <a href="{{ route('erp.transfers', ['status' => $s]) }}" class="{{ request('status') === $s ? 'on' : '' }}">{{ Fmt::status($s)[1] }} <span class="n">{{ $statusCounts[$s] ?? 0 }}</span></a>
      @endforeach
    </div>
    <div class="search-input grow"><x-icon name="search" size="sm"/><input class="form-control" placeholder="Filter this page…" data-table-search="#tr-table" aria-label="Filter transfers"></div>
  </div>
  @if($transfers->count())
  <div class="table-wrap"><table class="table table-hover" id="tr-table">
    <thead><tr><th style="width:36px"></th><th data-sort>Transfer</th><th>Route</th><th class="text-end" data-sort="num">Units</th><th>Progress</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    @foreach($transfers as $t)
      @php($units = $t->lines->sum('qty'))
      @php($rcv = $t->lines->sum('received_qty'))
      @php($dmg = $t->lines->sum('damaged_qty'))
      @php($pos = array_search($t->status === 'partially_received' ? 'in_transit' : $t->status, $flow))
      @php($receivable = in_array($t->status, ['in_transit', 'partially_received']) && ! $u->hasRole('staff', 'viewer', 'accounts_manager'))
      <tr>
        <td><button type="button" class="icon-btn" data-expand="tr-{{ $t->id }}" aria-expanded="false" aria-label="Show lines"><x-icon name="chev-right" size="sm" class="chev"/></button></td>
        <td><span class="cell-main mono">{{ $t->number }}</span><span class="cell-sub">{{ $t->created_at->format('d M Y') }}@if($t->remarks) · {{ Str::limit($t->remarks, 40) }}@endif</span></td>
        <td><div class="d-flex align-items-center gap-2 flex-wrap"><span>{{ $t->from->name }}</span><x-icon name="arrow-right" size="sm" class="text-muted"/><span class="fw-semibold">{{ $t->to->name }}</span></div></td>
        <td class="text-end num" data-v="{{ $units }}">{{ Fmt::num($units) }}@if($rcv || $dmg)<span class="cell-sub">{{ $rcv }} recv · {{ $dmg }} dmg</span>@endif</td>
        <td>
          <div class="steps" aria-label="Progress">
            @foreach($flow as $i => $s)
              @if($i)<span class="ln {{ $i <= $pos ? 'done' : '' }}"></span>@endif
              <span class="st {{ $i < $pos || ($i === $pos && $s === 'received') ? 'done' : ($i === $pos ? 'cur' : '') }}" data-tip="{{ Fmt::status($s)[1] }}@if($s === 'in_transit' && $t->dispatched_at)|{{ $t->dispatched_at->format('d M H:i') }}@elseif($s === 'received' && $t->received_at)|{{ $t->received_at->format('d M H:i') }}@endif"><span class="b">@if($i < $pos || ($i === $pos && $s === 'received'))<x-icon name="check" size="sm" style="width:11px;height:11px"/>@else{{ $i + 1 }}@endif</span></span>
            @endforeach
          </div>
        </td>
        <td><x-erp.status :value="$t->status"/></td>
        <td class="actions">
          @if($t->status === 'draft' && $u->hasRole('admin'))
            <form method="post" action="{{ route('erp.transfers.action', [$t, 'approve']) }}" class="d-inline" data-confirm="Approve {{ $t->number }} for dispatch? No stock moves until it is dispatched." data-confirm-ok="Approve">@csrf<button class="btn btn-sm btn-success"><x-icon name="check" size="sm"/>Approve</button></form>
          @elseif($t->status === 'approved' && $u->hasRole('admin', 'inventory_manager'))
            <form method="post" action="{{ route('erp.transfers.action', [$t, 'dispatch']) }}" class="d-inline" data-confirm="Dispatch {{ $t->number }}? {{ Fmt::num($units) }} units leave {{ $t->from->name }} and move to In transit." data-confirm-ok="Dispatch">@csrf<button class="btn btn-sm btn-primary"><x-icon name="truck" size="sm"/>Dispatch</button></form>
          @elseif($receivable)
            <button type="button" class="btn btn-sm btn-primary" data-open="receive-{{ $t->id }}"><x-icon name="package-check" size="sm"/>Receive</button>
          @endif
        </td>
      </tr>
      <tr class="subrow" id="tr-{{ $t->id }}" hidden><td colspan="7">
        <div class="p-3"><table class="table table-sm mb-0" style="background:var(--surface);border-radius:8px">
          <thead><tr><th>Product</th><th class="text-end">Dispatched</th><th class="text-end">Received</th><th class="text-end">Damaged</th><th class="text-end">Pending</th></tr></thead>
          <tbody>@foreach($t->lines as $l)<tr><td>{{ $l->product->name }}</td><td class="text-end num">{{ $l->qty }}</td><td class="text-end num">{{ $l->received_qty }}</td><td class="text-end num">{{ $l->damaged_qty }}</td><td class="text-end num fw-semibold">{{ $l->qty - $l->received_qty - $l->damaged_qty }}</td></tr>@endforeach</tbody>
        </table></div>
      </td></tr>
    @endforeach
    <tr class="no-results"><td colspan="7"><x-erp.empty icon="search" title="No matching transfers"/></td></tr>
    </tbody>
  </table></div>
  <div class="pager">{{ $transfers->links() }}</div>
  @else
    <x-erp.empty icon="truck" :title="request('status') ? 'No '.strtolower(Fmt::status(request('status'))[1]).' transfers' : 'No transfers yet'" text="Transfers move stock from a warehouse to a distributor (or between warehouses) with an approval step.">
      @if($canCreate)<button type="button" class="btn btn-primary" data-open="new-transfer"><x-icon name="plus" size="sm"/>New transfer</button>@endif
    </x-erp.empty>
  @endif
</section>

@foreach($transfers as $t)
  @if(in_array($t->status, ['in_transit', 'partially_received']) && ! $u->hasRole('staff', 'viewer', 'accounts_manager'))
    <x-erp.modal :id="'receive-'.$t->id" :title="'Receive '.$t->number" :subtitle="'From '.$t->from->name.' → '.$t->to->name.'. Unreceived quantity stays in transit for a later receipt.'"
      :action="route('erp.transfers.action', [$t, 'receive'])" submit="Post receipt" size="wide">
      <div class="table-wrap"><table class="table table-sm">
        <thead><tr><th>Product</th><th class="text-end">Pending</th><th style="width:130px">Received OK</th><th style="width:130px">Damaged</th></tr></thead>
        <tbody>@foreach($t->lines as $l)@php($pending = $l->qty - $l->received_qty - $l->damaged_qty)
          <tr><td>{{ $l->product->name }}</td><td class="text-end num">{{ $pending }}</td>
            <td><input name="received[{{ $l->id }}]" type="number" min="0" max="{{ $pending }}" value="{{ $pending }}" class="form-control form-control-sm text-end" aria-label="Received {{ $l->product->name }}" @disabled(! $pending)></td>
            <td><input name="damaged[{{ $l->id }}]" type="number" min="0" max="{{ $pending }}" value="0" class="form-control form-control-sm text-end" aria-label="Damaged {{ $l->product->name }}" @disabled(! $pending)></td></tr>
        @endforeach</tbody>
      </table></div>
      <div class="callout mt-2"><x-icon name="info" size="sm"/>Received + damaged may not exceed the pending quantity. Damaged units are posted to the Damaged location.</div>
    </x-erp.modal>
  @endif
@endforeach

@if($canCreate)
  <x-erp.modal id="new-transfer" title="New transfer" subtitle="Saved as a draft. An admin approves it before dispatch." :action="route('erp.transfers.store')" submit="Create draft" size="wide">
    <div class="row g-3 mb-3">
      <x-erp.field form="new-transfer" class="col-md-4" name="from_location_id" label="From" required id="nt-from">
        <select id="nt-from" name="from_location_id" class="form-select">@foreach($locations as $l)<option value="{{ $l->id }}" @selected((old('from_location_id') ?? request('from')) == $l->id)>{{ $l->name }} · {{ Fmt::label($l->type) }}</option>@endforeach</select>
      </x-erp.field>
      <x-erp.field form="new-transfer" class="col-md-4" name="to_location_id" label="To" required id="nt-to">
        <select id="nt-to" name="to_location_id" class="form-select">@foreach($locations as $l)<option value="{{ $l->id }}" @selected(old('to_location_id') || request('to') ? (old('to_location_id') ?? request('to')) == $l->id : $l->type === 'distributor')>{{ $l->name }} · {{ Fmt::label($l->type) }}</option>@endforeach</select>
      </x-erp.field>
      <x-erp.field form="new-transfer" class="col-md-4" name="remarks" label="Remarks / vehicle no." maxlength="255" placeholder="MH-04-AB-1234"/>
    </div>
    @if($u->hasRole('admin'))
      <input type="hidden" name="approve_now" value="0">
      <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="approve_now" value="1" @checked(old('_form') === 'new-transfer' ? old('approve_now') : true)> <span class="form-check-label">Approve now (I am an admin) — skips the separate approval step</span></label>
    @endif
    <div class="d-flex justify-content-between align-items-end mb-2"><div class="form-section mb-0">Quantities</div>
      <div class="search-input" style="width:220px"><x-icon name="search" size="sm"/><input class="form-control form-control-sm" placeholder="Find product…" oninput="const q=this.value.toLowerCase();this.closest('.dlg-body').querySelectorAll('.qty-item').forEach(i=>i.hidden=!i.textContent.toLowerCase().includes(q))" aria-label="Find product"></div></div>
    <div class="qty-grid">
      @foreach($products as $p)
        <label class="qty-item"><span class="info"><b>{{ $p->name }}</b><span class="mono">{{ $p->sku }} · {{ $p->unit }}</span></span>
          <input name="lines[{{ $p->id }}]" type="number" min="0" class="form-control form-control-sm" placeholder="0" value="{{ old('_form') === 'new-transfer' ? old('lines.'.$p->id) : '' }}" aria-label="{{ $p->name }} quantity"></label>
      @endforeach
    </div>
  </x-erp.modal>
@endif
@endsection
