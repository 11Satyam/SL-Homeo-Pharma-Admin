@extends('erp.layout')
@use('App\Support\Fmt')
@section('title', 'Orders & Deliveries')
@section('crumb')<span>Sales</span>@endsection
@section('subtitle', 'Confirming an order reserves stock; delivery deducts it once and issues the invoice for the delivered quantity.')
@php($u = auth()->user())
@php($canAct = $u->hasRole('admin', 'distributor', 'staff'))
@php($open = ['confirmed', 'packed', 'out_for_delivery'])
@php($flow = ['confirmed', 'packed', 'out_for_delivery', 'delivered'])
@section('actions')
  @if($canAct && $shops->count())<button type="button" class="btn btn-primary" data-open="new-order"><x-icon name="plus" size="sm"/>New order</button>@endif
@endsection
@section('content')
<section class="panel">
  <form class="toolbar" method="get">
    <div class="seg">
      @php($tabs = ['' => ['All', $statusCounts->sum()], 'open' => ['Open', collect($open)->sum(fn ($s) => $statusCounts[$s] ?? 0)], 'delivered' => ['Delivered', ($statusCounts['delivered'] ?? 0)], 'partially_delivered' => ['Partial', ($statusCounts['partially_delivered'] ?? 0)], 'failed' => ['Failed', ($statusCounts['failed'] ?? 0)], 'cancelled' => ['Cancelled', ($statusCounts['cancelled'] ?? 0)]])
      @foreach($tabs as $k => [$l, $n])<a href="{{ request()->fullUrlWithQuery(['status' => $k ?: null, 'page' => null]) }}" class="{{ request('status', '') === $k ? 'on' : '' }}">{{ $l }} <span class="n">{{ $n }}</span></a>@endforeach
    </div>
    <div class="search-input grow"><x-icon name="search" size="sm"/><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Order number or shop…  ( / )" aria-label="Search orders"></div>
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <button class="btn btn-light">Search</button>
    @if(request()->hasAny(['q', 'status']))<a href="{{ route('erp.orders') }}" class="btn btn-ghost">Reset</a>@endif
  </form>
  @if($orders->count())
  <div class="table-wrap"><table class="table table-hover">
    <thead><tr><th style="width:36px"></th><th>Order</th><th>Shop</th><th>Staff</th><th class="text-end">Items</th><th class="text-end">Amount</th><th>Progress</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    @foreach($orders as $o)
      @php($pos = array_search(in_array($o->status, ['delivered', 'partially_delivered']) ? 'delivered' : $o->status, $flow))
      @php($live = $canAct && in_array($o->status, $open) && ! ($u->hasRole('staff') && $o->staff_id !== $u->id))
      @php($canReturn = $o->invoice && $u->hasRole('admin', 'distributor') && $o->lines->sum('delivered_qty') > $o->lines->sum('returned_qty'))
      <tr id="order-{{ $o->id }}">
        <td><button type="button" class="icon-btn" data-expand="ol-{{ $o->id }}" aria-expanded="false" aria-label="Show order lines"><x-icon name="chev-right" size="sm" class="chev"/></button></td>
        <td><span class="cell-main mono">{{ $o->number }}</span><span class="cell-sub">{{ $o->created_at->format('d M Y, H:i') }}</span></td>
        <td><a href="{{ route('erp.shop', $o->shop) }}" class="fw-semibold">{{ $o->shop->name }}</a><span class="cell-sub">{{ $o->shop->area }}</span></td>
        <td>@if($o->staff)<span class="d-inline-flex align-items-center gap-2"><span class="avatar sm" style="background: {{ Fmt::avatarColor($o->staff->name) }}">{{ Fmt::initials($o->staff->name) }}</span>{{ $o->staff->name }}</span>@else<span class="text-muted">Unassigned</span>@endif</td>
        <td class="text-end num">{{ $o->lines->count() }}<span class="cell-sub">{{ Fmt::num($o->lines->sum('qty')) }} units</span></td>
        <td class="text-end num fw-semibold">{{ Fmt::money($o->total) }}@if($o->invoice)<span class="cell-sub"><a href="{{ route('erp.invoice', $o->invoice) }}">{{ $o->invoice->number }}</a></span>@endif</td>
        <td>
          @if($pos !== false)
          <div class="steps">@foreach($flow as $i => $s)@if($i)<span class="ln {{ $i <= $pos ? 'done' : '' }}"></span>@endif<span class="st {{ $i < $pos || ($i === $pos && $s === 'delivered') ? 'done' : ($i === $pos ? 'cur' : '') }}" data-tip="{{ Fmt::status($s)[1] }}"><span class="b">@if($i < $pos || ($i === $pos && $s === 'delivered'))<x-icon name="check" size="sm" style="width:11px;height:11px"/>@else{{ $i + 1 }}@endif</span></span>@endforeach</div>
          @else<span class="text-muted small">{{ $o->failure_reason ?: 'Stopped' }}</span>@endif
        </td>
        <td><x-erp.status :value="$o->status"/></td>
        <td class="actions">
          @if($live || $canReturn)
          <details class="menu">
            <summary class="btn btn-sm btn-light">Actions<x-icon name="chev-down" size="sm"/></summary>
            <div class="menu-pop">
              @if($live)
                @foreach(['confirmed' => 'packed', 'packed' => 'out_for_delivery'] as $from => $to)
                  @if($o->status === $from)<form method="post" action="{{ route('erp.orders.action', [$o, $to]) }}">@csrf<button class="mi"><x-icon :name="$to === 'packed' ? 'box' : 'truck'" size="sm"/>Mark {{ strtolower(Fmt::status($to)[1]) }}</button></form>@endif
                @endforeach
                <button type="button" class="mi" data-open="deliver-{{ $o->id }}"><x-icon name="package-check" size="sm"/>Record delivery…</button>
                <div class="sep"></div>
                <button type="button" class="mi" data-open="fail-{{ $o->id }}"><x-icon name="x-circle" size="sm"/>Mark delivery failed…</button>
                <form method="post" action="{{ route('erp.orders.action', [$o, 'cancelled']) }}" data-confirm="Cancel {{ $o->number }}? Reserved stock is released back to available." data-confirm-ok="Cancel order" data-tone="danger">@csrf<button class="mi danger"><x-icon name="ban" size="sm"/>Cancel order</button></form>
              @endif
              @if($canReturn)<button type="button" class="mi" data-open="return-{{ $o->id }}"><x-icon name="undo" size="sm"/>Process return…</button>@endif
            </div>
          </details>
          @endif
        </td>
      </tr>
      <tr class="subrow" id="ol-{{ $o->id }}" hidden><td colspan="9"><div class="p-3">
        <table class="table table-sm mb-2" style="background:var(--surface);border-radius:8px">
          <thead><tr><th>Product</th><th class="text-end">Ordered</th><th class="text-end">Price</th><th class="text-end">GST</th><th class="text-end">Delivered</th><th class="text-end">Returned</th><th class="text-end">Line total</th></tr></thead>
          <tbody>@foreach($o->lines as $l)<tr><td>{{ $l->product->name }}</td><td class="text-end num">{{ $l->qty }}</td><td class="text-end num">{{ Fmt::money($l->price) }}</td><td class="text-end num">{{ (float) $l->tax_rate }}%</td><td class="text-end num">{{ $l->delivered_qty }}</td><td class="text-end num">{{ $l->returned_qty }}</td><td class="text-end num">{{ Fmt::money($l->price * $l->qty * (1 + $l->tax_rate / 100)) }}</td></tr>@endforeach</tbody>
        </table>
        <div class="d-flex flex-wrap gap-3 small text-muted">
          @if($o->remarks)<span><x-icon name="info" size="sm"/> {{ $o->remarks }}</span>@endif
          @if($o->failure_reason)<span class="text-danger"><x-icon name="alert" size="sm"/> {{ $o->failure_reason }}</span>@endif
          @if($o->delivered_at)<span><x-icon name="package-check" size="sm"/> Delivered {{ $o->delivered_at->format('d M Y, H:i') }}</span>@endif
        </div>
      </div></td></tr>
    @endforeach
    </tbody>
  </table></div>
  <div class="pager">{{ $orders->links() }}</div>
  @else
    <x-erp.empty icon="cart" :title="request()->hasAny(['q','status']) ? 'No orders match your filters' : 'No orders yet'" :text="request()->hasAny(['q','status']) ? 'Try another status or search term.' : 'Orders from this panel and the field staff app appear here.'">
      @if(request()->hasAny(['q','status']))<a href="{{ route('erp.orders') }}" class="btn btn-light">Clear filters</a>@elseif($canAct && $shops->count())<button type="button" class="btn btn-primary" data-open="new-order"><x-icon name="plus" size="sm"/>New order</button>@endif
    </x-erp.empty>
  @endif
</section>

{{-- Per-order dialogs --}}
@foreach($orders as $o)
  @if($canAct && in_array($o->status, $open) && ! ($u->hasRole('staff') && $o->staff_id !== $u->id))
    <x-erp.modal :id="'deliver-'.$o->id" :title="'Record delivery · '.$o->number" :subtitle="$o->shop->name.'. Enter what the shop accepted; the rest is released back to stock.'"
      :action="route('erp.orders.action', [$o, 'deliver'])" submit="Post delivery & invoice" submit-class="btn-success" size="wide"
      confirm="Post this delivery? Stock is deducted and an invoice is issued for the delivered quantity. This cannot be undone (use returns for corrections).">
      <table class="table table-sm"><thead><tr><th>Product</th><th class="text-end">Ordered</th><th style="width:140px">Delivered</th></tr></thead>
        <tbody>@foreach($o->lines as $l)<tr><td>{{ $l->product->name }}</td><td class="text-end num">{{ $l->qty }}</td>
          <td><input name="delivered[{{ $l->id }}]" type="number" min="0" max="{{ $l->qty }}" value="{{ $l->qty }}" class="form-control form-control-sm text-end" aria-label="Delivered {{ $l->product->name }}"></td></tr>@endforeach</tbody></table>
      <div class="callout"><x-icon name="info" size="sm"/>If nothing was delivered, use “Mark delivery failed” instead.</div>
    </x-erp.modal>
    <x-erp.modal :id="'fail-'.$o->id" :title="'Delivery failed · '.$o->number" subtitle="Reserved stock is released. The order cannot be delivered afterwards."
      :action="route('erp.orders.action', [$o, 'failed'])" submit="Mark failed" submit-class="btn-danger" size="narrow">
      <x-erp.field :form="'fail-'.$o->id" name="reason" label="Reason" required maxlength="255" placeholder="Shop closed · customer refused · address not found" list="fail-reasons"/>
    </x-erp.modal>
  @endif
  @if($o->invoice && $u->hasRole('admin', 'distributor') && $o->lines->sum('delivered_qty') > $o->lines->sum('returned_qty'))
    <x-erp.modal :id="'return-'.$o->id" :title="'Process return · '.$o->number" subtitle="After inspection: sellable units return to distributor stock, damaged units to the damage bin. A credit note is issued automatically."
      :action="route('erp.orders.action', [$o, 'return'])" submit="Post return & credit note" confirm="Post this inspected return? Stock and a credit note are posted immediately.">
      <div class="row g-3">
        <x-erp.field :form="'return-'.$o->id" class="col-12" name="line_id" label="Product" required :id="'rl-'.$o->id">
          <select id="rl-{{ $o->id }}" name="line_id" class="form-select">@foreach($o->lines->filter(fn ($l) => $l->delivered_qty > $l->returned_qty) as $l)<option value="{{ $l->id }}">{{ $l->product->name }} — {{ $l->delivered_qty - $l->returned_qty }} returnable</option>@endforeach</select>
        </x-erp.field>
        <x-erp.field :form="'return-'.$o->id" class="col-6" name="sellable" label="Good, back to stock" type="number" min="0" value="0" required/>
        <x-erp.field :form="'return-'.$o->id" class="col-6" name="damaged" label="Damaged" type="number" min="0" value="0" required/>
        <x-erp.field :form="'return-'.$o->id" class="col-12" name="reason" label="Return reason" required maxlength="255" placeholder="Near expiry · wrong item · excess supply"/>
      </div>
    </x-erp.modal>
  @endif
@endforeach
<datalist id="fail-reasons"><option value="Shop closed"><option value="Customer refused delivery"><option value="Address not found"><option value="Payment not ready (COD)"></datalist>

@if($canAct && $shops->count())
  <x-erp.modal id="new-order" title="New order" subtitle="Prices and GST come from the product master. Stock is reserved at the distributor on confirmation." :action="route('erp.orders.store')" submit="Confirm order" size="wide">
    <input type="hidden" name="key" value="{{ Str::uuid() }}">
    <div class="row g-3 mb-3">
      <x-erp.field form="new-order" class="col-md-{{ $u->hasRole('staff') ? '12' : '7' }}" name="shop_id" label="Shop" required id="no-shop">
        <select id="no-shop" name="shop_id" class="form-select" required><option value="">Select shop…</option>@foreach($shops->sortBy('name') as $s)<option value="{{ $s->id }}" data-dist="{{ $s->distributor_id }}" @selected((old('shop_id') ?? request('shop')) == $s->id)>{{ $s->name }}{{ $s->area ? ' · '.$s->area : '' }}</option>@endforeach</select>
      </x-erp.field>
      @unless($u->hasRole('staff'))
        <x-erp.field form="new-order" class="col-md-5" name="staff_id" label="Delivery staff" id="no-staff">
          <select id="no-staff" name="staff_id" class="form-select"><option value="">Unassigned</option>@foreach($staff as $s)<option value="{{ $s->id }}" @selected(old('staff_id') == $s->id)>{{ $s->name }}</option>@endforeach</select>
        </x-erp.field>
      @endunless
    </div>
    <div class="d-flex justify-content-between align-items-center mb-2 gap-2 flex-wrap">
      <div class="form-section mb-0">Products</div>
      <div class="live-stock" data-stock-live hidden><span class="dot"></span><span data-stock-at>Live stock</span>
        <button type="button" class="btn btn-sm btn-ghost" data-stock-refresh data-tip="Refresh stock now"><x-icon name="undo" size="sm"/><span class="sr-only">Refresh stock</span></button></div>
    </div>
    <div class="qty-filters" data-qty-filters>
      <div class="search-input grow"><x-icon name="search" size="sm"/><input type="search" class="form-control form-control-sm" placeholder="Name, SKU or category…" data-f-q aria-label="Find product"></div>
      @php($cats = $products->pluck('category')->filter()->unique()->sort()->values())
      @if($cats->count())
        <select class="form-select form-select-sm" data-f-cat aria-label="Category"><option value="">All categories</option>@foreach($cats as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select>
      @endif
      @php($rates = $products->pluck('tax_rate')->map(fn ($t) => (float) $t)->unique()->sort()->values())
      @if($rates->count() > 1)
        <select class="form-select form-select-sm" data-f-gst aria-label="GST rate"><option value="">Any GST</option>@foreach($rates as $t)<option value="{{ $t }}">GST {{ $t }}%</option>@endforeach</select>
      @endif
      <select class="form-select form-select-sm" data-f-sort aria-label="Sort products">
        <option value="name">Sort: Name A–Z</option><option value="stock">Most in stock</option><option value="low">Least in stock</option><option value="price">Price: low to high</option><option value="-price">Price: high to low</option>
      </select>
    </div>
    <div class="chips mb-2" role="group" aria-label="Filter by stock" data-f-chips>
      @foreach(['' => 'All', 'in' => 'In stock', 'low' => 'Low', 'out' => 'Out of stock', 'picked' => 'In this order', 'short' => 'Short'] as $k => $l)
        <button type="button" class="chip {{ $k === '' ? 'on' : '' }} {{ $k === 'short' ? 'bad' : '' }}" data-chip="{{ $k }}" aria-pressed="{{ $k === '' ? 'true' : 'false' }}" @if(in_array($k, ['in', 'low', 'out'])) data-needs-shop @endif>{{ $l }} <span class="n" data-chip-n></span></button>
      @endforeach
      <button type="button" class="btn btn-sm btn-ghost ms-auto" data-f-reset hidden>Clear filters</button>
    </div>
    <div class="qty-grid">
      @foreach($products as $p)
        <label class="qty-item" data-name="{{ Str::lower($p->name) }}" data-search="{{ Str::lower($p->name.' '.$p->sku.' '.$p->category) }}" data-cat="{{ $p->category }}" data-gst="{{ (float) $p->tax_rate }}">
          <span class="info"><b title="{{ $p->name }}">{{ $p->name }}</b>
            <span>{{ Fmt::money($p->selling_price) }} + {{ (float) $p->tax_rate }}% · {{ $p->unit }}@if($p->category) · {{ $p->category }}@endif</span>
            <span class="avail" data-avail></span>
            <span class="meter" data-meter hidden><i></i></span>
            <span class="line-total" data-line-total></span></span>
          <input name="lines[{{ $p->id }}]" type="number" min="0" step="1" inputmode="numeric" class="form-control form-control-sm" placeholder="0" data-price="{{ $p->selling_price }}" data-tax="{{ $p->tax_rate }}" data-product="{{ $p->id }}" data-reorder="{{ $p->reorder_level }}"
            value="{{ old('_form') === 'new-order' ? old('lines.'.$p->id) : '' }}" aria-label="{{ $p->name }} quantity"></label>
      @endforeach
      <div class="qty-none" data-qty-none hidden>No products match these filters. <button type="button" class="btn btn-link btn-sm p-0" data-f-reset>Clear filters</button></div>
    </div>
    <script type="application/json" data-order-stock data-url="{{ route('erp.orders.stock') }}">@json(['stock' => $stock, 'locations' => $stockLocations, 'at' => now()->toIso8601String()])</script>
    <div class="callout mt-3" data-stock-pick><x-icon name="info" size="sm"/>Select the shop to see how much of each product is in stock at its distributor. Stock refreshes live while this form is open.</div>
    <div class="alert alert-warning mt-3 mb-0" role="alert" data-stock-help hidden>
      <x-icon name="alert" size="sm"/>
      <div class="flex-grow-1">
        <b>Not enough stock for this order</b>
        <ul class="mb-2 mt-1 ps-3" data-stock-list></ul>
        <div class="small fw-semibold">How to fix</div>
        <ol class="small mb-2 ps-3">
          <li>Lower each short quantity to what is in stock (button below). Take the rest as a new order once stock arrives.</li>
          @if($u->hasRole('admin', 'inventory_manager'))
            <li>Or send stock to this distributor first: <a href="{{ route('erp.transfers', ['new' => 1]) }}" data-stock-transfer>create a transfer</a> from the warehouse, then approve, dispatch and receive it.</li>
          @else
            <li>Or ask the company's inventory manager for a stock transfer. It appears under <a href="{{ route('erp.transfers') }}">Transfers</a>; once it is marked received, the stock can be ordered.</li>
          @endif
          <li>Stock set aside for other open orders is not free. Cancelling an old order that will not be delivered releases it.</li>
        </ol>
        <button type="button" class="btn btn-sm btn-outline-warning" data-stock-fit>Set short items to what is in stock</button>
      </div>
    </div>
    <x-erp.field form="new-order" class="mt-3" name="remarks" label="Remarks (optional)" maxlength="500" placeholder="Delivery instructions, preferred time…"/>
    <div class="order-sum mt-3">
      <div><span class="text-muted" data-order-count>0 products</span><span class="cell-sub" data-order-units></span></div>
      <div class="sum-figs"><span>Subtotal <b data-order-sub>₹0.00</b></span><span>GST <b data-order-gst>₹0.00</b></span>
        <span>Total incl. GST <b class="fs-5 ms-1" data-order-total>₹0.00</b></span></div>
    </div>
    <div class="small text-danger mt-2" data-order-block hidden><x-icon name="alert" size="sm"/> <span></span></div>
  </x-erp.modal>
@endif
@endsection
