@extends('erp.layout')
@use('App\Support\Fmt')
@section('title', 'Warehouse & Stock')
@section('crumb')<span>Inventory</span>@endsection
@section('subtitle', 'Live balances from the stock ledger. Available = on hand − reserved for confirmed orders.')
@php($canPost = auth()->user()->hasRole('admin', 'inventory_manager'))
@php($byLoc = $balances->groupBy('location_id'))
@php($isAdmin = auth()->user()->hasRole('admin'))
@section('actions')
  @if($canPost)
    <button type="button" class="btn btn-light" data-open="new-warehouse"><x-icon name="warehouse" size="sm"/>New warehouse</button>
    @if($isAdmin && $warehouses->isNotEmpty())<button type="button" class="btn btn-light" data-open="manage-warehouses"><x-icon name="ban" size="sm"/>Delete warehouse</button>@endif
    <button type="button" class="btn btn-light" data-open="write-off" @disabled($warehouses->isEmpty())><x-icon name="alert" size="sm"/>Write-off</button>
    <button type="button" class="btn btn-primary" data-open="receive-stock" @disabled($warehouses->isEmpty())><x-icon name="download" size="sm"/>Receive stock</button>
  @endif
@endsection
@section('content')
@if($byLoc->count())
<div class="row g-3 mb-3">
  @foreach($byLoc->take(8) as $rows)
    @php($loc = $rows->first()->location)
    @php($onHand = $rows->sum('qty'))
    @php($reserved = $rows->sum('reserved'))
    <div class="col-sm-6 col-xl-3">
      <div class="stat">
        <div class="label"><span class="tile-ic {{ ['warehouse' => '', 'distributor' => 'ok', 'transit' => 'violet'][$loc->type] ?? '' }}"><x-icon :name="['warehouse' => 'warehouse', 'distributor' => 'building', 'transit' => 'truck'][$loc->type] ?? 'box'" size="sm"/></span>
          <span class="text-truncate">{{ $loc->name }}</span><span class="tag ms-auto">{{ Fmt::label($loc->type) }}</span></div>
        <div class="value">{{ Fmt::num($onHand) }} <span class="fs-6 fw-normal text-muted">units</span></div>
        <div class="meter mt-2" data-tip="{{ $loc->name }}|{{ Fmt::num($reserved) }} reserved of {{ Fmt::num($onHand) }}"><span style="width: {{ $onHand > 0 ? min(100, $reserved / $onHand * 100) : 0 }}%"></span></div>
        <div class="meta">{{ $rows->count() }} SKUs · {{ Fmt::num($reserved) }} reserved</div>
      </div>
    </div>
  @endforeach
</div>
@endif

<section class="panel">
  <div class="toolbar">
    <div class="search-input grow"><x-icon name="search" size="sm"/><input class="form-control" placeholder="Filter by product or location…  ( / )" data-table-search="#stock-table" aria-label="Filter stock"></div>
    <span class="text-muted small">{{ $balances->count() }} balances</span>
  </div>
  @if($balances->count())
  <div class="table-wrap tall"><table class="table table-hover" id="stock-table">
    <thead><tr><th data-sort>Product</th><th data-sort>Location</th><th class="text-end" data-sort="num">On hand</th><th class="text-end" data-sort="num">Reserved</th><th class="text-end" data-sort="num">Available</th><th style="width:160px">Utilisation</th><th>Status</th></tr></thead>
    <tbody>
    @foreach($balances as $b)
      @php($avail = $b->qty - $b->reserved)
      @php($low = $b->location->type === 'warehouse' && $b->product->reorder_level > 0 && $b->qty <= $b->product->reorder_level)
      <tr>
        <td><span class="cell-main">{{ $b->product->name }}</span><span class="cell-sub mono">{{ $b->product->sku }}</span></td>
        <td>{{ $b->location->name }}<span class="cell-sub">{{ Fmt::label($b->location->type) }}</span></td>
        <td class="text-end num" data-v="{{ $b->qty }}">{{ Fmt::num($b->qty) }}</td>
        <td class="text-end num text-muted" data-v="{{ $b->reserved }}">{{ Fmt::num($b->reserved) }}</td>
        <td class="text-end num fw-semibold" data-v="{{ $avail }}">{{ Fmt::num($avail) }}</td>
        <td><div class="meter {{ $b->qty > 0 && $b->reserved / max(1, $b->qty) > .8 ? 'warn' : '' }}" data-tip="{{ $b->product->name }}|{{ $b->qty > 0 ? round($b->reserved / $b->qty * 100) : 0 }}% reserved"><span style="width: {{ $b->qty > 0 ? min(100, $b->reserved / $b->qty * 100) : 0 }}%"></span></div></td>
        <td>@if($low)<x-erp.status value="low" :label="'Low · reorder '.$b->product->reorder_level"/>@elseif($avail <= 0)<x-erp.status value="out" label="Fully reserved"/>@else<x-erp.status value="healthy"/>@endif</td>
      </tr>
    @endforeach
    <tr class="no-results"><td colspan="7"><x-erp.empty icon="search" title="No matching balances" text="Try a different product or location name."/></td></tr>
    </tbody>
  </table></div>
  @else
    <x-erp.empty icon="warehouse" title="No stock posted yet" text="Create a warehouse, then post opening stock or a verified goods receipt to start the ledger.">
      @if($canPost)<button type="button" class="btn btn-primary" data-open="{{ $warehouses->isEmpty() ? 'new-warehouse' : 'receive-stock' }}"><x-icon name="plus" size="sm"/>{{ $warehouses->isEmpty() ? 'Create warehouse' : 'Receive stock' }}</button>@endif
    </x-erp.empty>
  @endif
</section>

@if($canPost)
  <x-erp.modal id="receive-stock" title="Receive stock" subtitle="Posts an inbound ledger entry (External → Warehouse). Each submission posts once." :action="route('erp.stock.receive')" submit="Post to ledger">
    <input type="hidden" name="key" value="{{ Str::uuid() }}">
    <div class="row g-3">
      <x-erp.field form="receive-stock" class="col-md-6" name="location_id" label="Warehouse" required id="rs-loc">
        <select id="rs-loc" name="location_id" class="form-select" required>@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected((old('location_id') ?? request('location')) == $w->id)>{{ $w->name }}</option>@endforeach</select>
      </x-erp.field>
      <x-erp.field form="receive-stock" class="col-md-6" name="type" label="Entry type" required id="rs-type">
        <select id="rs-type" name="type" class="form-select"><option value="receipt">Verified goods receipt (GRN)</option><option value="opening" @selected(old('type') === 'opening')>Opening balance</option></select>
      </x-erp.field>
      <x-erp.field form="receive-stock" class="col-md-8" name="product_id" label="Product" required id="rs-prod">
        <select id="rs-prod" name="product_id" class="form-select" required><option value="">Select product…</option>@foreach($products as $p)<option value="{{ $p->id }}" @selected((old('product_id') ?? request('product')) == $p->id)>{{ $p->name }} ({{ $p->sku }})</option>@endforeach</select>
      </x-erp.field>
      <x-erp.field form="receive-stock" class="col-md-4" name="qty" label="Quantity" type="number" min="1" required/>
      <x-erp.field form="receive-stock" class="col-12" name="reason" label="Reference / note" maxlength="255" placeholder="GRN-2410-0042 · Supplier invoice no."/>
    </div>
  </x-erp.modal>

  <x-erp.modal id="write-off" title="Damage / loss write-off" subtitle="Moves stock to the Damaged location. This cannot be edited later — reverse it from the ledger if needed."
    :action="route('erp.stock.adjust')" submit="Post write-off" submit-class="btn-danger" confirm="Write off this stock as damaged? The movement is permanent and audit-logged." tone="danger">
    <input type="hidden" name="key" value="{{ Str::uuid() }}">
    <div class="row g-3">
      <x-erp.field form="write-off" class="col-md-6" name="location_id" label="Warehouse" required id="wo-loc">
        <select id="wo-loc" name="location_id" class="form-select">@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select>
      </x-erp.field>
      <x-erp.field form="write-off" class="col-md-6" name="product_id" label="Product" required id="wo-prod">
        <select id="wo-prod" name="product_id" class="form-select">@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
      </x-erp.field>
      <x-erp.field form="write-off" class="col-md-4" name="qty" label="Quantity" type="number" min="1" required/>
      <x-erp.field form="write-off" class="col-md-8" name="reason" label="Reason" required maxlength="255" placeholder="Broken in handling, expired batch B2304…"/>
    </div>
  </x-erp.modal>

  @if($isAdmin)
    <dialog id="manage-warehouses" class="modal-x" aria-labelledby="manage-warehouses-t">
      <div class="dlg-head">
        <div><h2 id="manage-warehouses-t">Delete a warehouse</h2><p>Only an empty warehouse with no open transfers can be deleted. Its past movements stay in the Stock Ledger.</p></div>
        <button type="button" class="icon-btn" data-close aria-label="Close"><x-icon name="x"/></button>
      </div>
      <div class="dlg-body">
        <table class="table mb-0"><thead><tr><th>Warehouse</th><th class="text-end">On hand</th><th></th></tr></thead><tbody>
        @foreach($warehouses as $w)
          @php($held = (int) $w->on_hand)
          <tr>
            <td>{{ $w->name }}</td>
            <td class="text-end num">{{ Fmt::num($held) }}</td>
            <td class="text-end">
              <form method="post" action="{{ route('erp.warehouses.delete', $w) }}" data-confirm="Delete warehouse &quot;{{ $w->name }}&quot;? It disappears from stock, receipts and transfers. This cannot be undone." data-confirm-ok="Delete warehouse" data-tone="danger">@csrf
                <button class="btn btn-sm btn-outline-danger" @disabled($held !== 0) @if($held !== 0) data-tip="Move or write off its {{ Fmt::num($held) }} units first" @endif>Delete</button>
              </form>
            </td>
          </tr>
        @endforeach
        </tbody></table>
      </div>
      <div class="dlg-foot"><button type="button" class="btn btn-light" data-close>Close</button></div>
    </dialog>
  @endif

  <x-erp.modal id="new-warehouse" title="New warehouse" subtitle="Creates an internal stock location for receipts and transfers." :action="route('erp.warehouses.store')" submit="Create warehouse" size="narrow">
    <x-erp.field form="new-warehouse" name="name" label="Warehouse name" required maxlength="100" placeholder="e.g. Bhiwandi Central Warehouse"/>
  </x-erp.modal>
@endif
@endsection
