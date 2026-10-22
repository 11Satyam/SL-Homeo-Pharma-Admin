@extends('erp.layout')
@use('App\Support\Fmt')
@section('title', 'Products')
@section('crumb')<span>Inventory</span>@endsection
@section('subtitle', 'Product master: pricing, tax and reorder levels used by orders, invoices and stock alerts.')
@php($canEdit = auth()->user()->hasRole('admin', 'inventory_manager'))
@php($units = ['pcs', 'box', 'carton', 'pack', 'strip', 'bottle', 'tube', 'vial'])
@section('actions')
  @if($canEdit)<button type="button" class="btn btn-primary" data-open="add-product"><x-icon name="plus" size="sm"/>Add product</button>@endif
@endsection
@section('content')
<section class="panel">
  <form class="toolbar" method="get">
    <div class="seg" role="tablist" aria-label="Status">
      @foreach(['' => ['All', $counts['all']], 'active' => ['Active', $counts['active']], 'inactive' => ['Inactive', $counts['inactive']]] as $k => [$l, $n])
        <a href="{{ request()->fullUrlWithQuery(['status' => $k ?: null, 'page' => null]) }}" class="{{ request('status', '') === $k ? 'on' : '' }}">{{ $l }} <span class="n">{{ $n }}</span></a>
      @endforeach
    </div>
    <div class="search-input grow"><x-icon name="search" size="sm"/><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search name or SKU…  ( / )" aria-label="Search products"></div>
    <select name="category" class="form-select" style="max-width:200px" aria-label="Category" onchange="this.form.requestSubmit()">
      <option value="">All categories</option>@foreach($categories as $c)<option @selected(request('category') === $c)>{{ $c }}</option>@endforeach
    </select>
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <button class="btn btn-light"><x-icon name="filter" size="sm"/>Apply</button>
    @if(request()->hasAny(['q', 'category', 'status']))<a href="{{ route('erp.products') }}" class="btn btn-ghost">Reset</a>@endif
  </form>
  @if($products->count())
  <div class="table-wrap"><table class="table table-hover" id="products-table">
    <thead><tr><th data-sort>Product</th><th>Category</th><th class="text-end" data-sort="num">Cost</th><th class="text-end" data-sort="num">Price</th><th class="text-end" data-sort="num">Margin</th><th class="text-end">GST</th><th class="text-end" data-sort="num">On hand</th><th class="text-end">Reorder at</th><th>Status</th>@if($canEdit)<th><span class="sr-only">Actions</span></th>@endif</tr></thead>
    <tbody>
    @foreach($products as $p)
      @php($margin = $p->selling_price > 0 ? ($p->selling_price - $p->cost_price) / $p->selling_price * 100 : 0)
      @php($low = $p->active && $p->reorder_level > 0 && (int) $p->on_hand <= $p->reorder_level)
      <tr>
        <td><span class="cell-main">{{ $p->name }}</span><span class="cell-sub mono">{{ $p->sku }} · per {{ $p->unit }}</span></td>
        <td>@if($p->category)<span class="tag">{{ $p->category }}</span>@else<span class="text-muted">—</span>@endif</td>
        <td class="text-end num" data-v="{{ $p->cost_price }}">{{ Fmt::money($p->cost_price) }}</td>
        <td class="text-end num fw-semibold" data-v="{{ $p->selling_price }}">{{ Fmt::money($p->selling_price) }}</td>
        <td class="text-end num" data-v="{{ $margin }}"><span class="{{ $margin < 10 ? 'text-danger' : '' }}">{{ number_format($margin, 1) }}%</span></td>
        <td class="text-end num">{{ rtrim(rtrim(number_format((float) $p->tax_rate, 2), '0'), '.') }}%</td>
        <td class="text-end num" data-v="{{ (int) $p->on_hand }}">{{ Fmt::num($p->on_hand) }} @if($low)<x-icon name="alert" size="sm" class="text-danger" data-tip="Low stock|At or below reorder level"/>@endif</td>
        <td class="text-end num text-muted">{{ Fmt::num($p->reorder_level) }}</td>
        <td><x-erp.status :value="$p->active ? 'active' : 'inactive'"/></td>
        @if($canEdit)
        <td class="actions"><button type="button" class="btn btn-sm btn-light" data-open="edit-product-{{ $p->id }}"><x-icon name="edit" size="sm"/>Edit</button></td>
        @endif
      </tr>
    @endforeach
    </tbody>
  </table></div>
  <div class="pager">{{ $products->links() }}</div>
  @else
    <x-erp.empty icon="pill" :title="request()->hasAny(['q','category','status']) ? 'No products match your filters' : 'No products yet'"
      :text="request()->hasAny(['q','category','status']) ? 'Try a different search term or clear the filters.' : 'Add your first product to start receiving stock and taking orders.'">
      @if(request()->hasAny(['q','category','status']))<a href="{{ route('erp.products') }}" class="btn btn-light">Clear filters</a>
      @elseif($canEdit)<button type="button" class="btn btn-primary" data-open="add-product"><x-icon name="plus" size="sm"/>Add product</button>@endif
    </x-erp.empty>
  @endif
</section>

@if($canEdit)
  @foreach(array_merge([null], $products->items()) as $p)
    @php($id = $p ? 'edit-product-'.$p->id : 'add-product')
    @php($val = fn ($k, $d = null) => old('_form') === $id ? old($k) : ($p ? $p->$k : $d))
    <x-erp.modal :id="$id" :title="$p ? 'Edit '.$p->name : 'Add product'" :subtitle="$p ? 'SKU '.$p->sku.' · price changes apply to new orders only' : 'Prices are tax-exclusive; GST is added on orders and invoices.'"
      :action="$p ? route('erp.products.update', $p) : route('erp.products.store')" :submit="$p ? 'Save changes' : 'Add product'" size="wide">
      <div class="row g-3">
        <x-erp.field :form="$id" class="col-md-6" name="name" label="Product name" required maxlength="255" :value="$val('name')" placeholder="e.g. Paracetamol 500mg (10 tabs)"/>
        <x-erp.field :form="$id" class="col-md-3" name="sku" label="SKU" required maxlength="64" :value="$val('sku')" placeholder="PCM500"/>
        <x-erp.field :form="$id" class="col-md-3" name="category" label="Category" maxlength="100" :value="$val('category')" list="cat-list"/>
        <x-erp.field :form="$id" class="col-md-3" name="unit" label="Unit" required :id="$id.'-unit'">
          <select name="unit" class="form-select" id="{{ $id }}-unit">@foreach($units as $x)<option @selected($val('unit', 'strip') === $x)>{{ $x }}</option>@endforeach</select>
        </x-erp.field>
        <x-erp.field :form="$id" class="col-md-3" name="cost_price" label="Cost price (₹)" type="number" step="0.01" min="0" required :value="$val('cost_price')"/>
        <x-erp.field :form="$id" class="col-md-3" name="selling_price" label="Selling price (₹)" type="number" step="0.01" min="0" required :value="$val('selling_price')"/>
        <x-erp.field :form="$id" class="col-md-3" name="tax_rate" label="GST %" type="number" step="0.01" min="0" max="100" required :value="$val('tax_rate', 12)"/>
        <x-erp.field :form="$id" class="col-md-3" name="reorder_level" label="Reorder level" type="number" min="0" required :value="$val('reorder_level', 0)" help="Warehouse alert threshold"/>
        <div class="col-md-9 d-flex align-items-end">
          @if($p)
            <input type="hidden" name="active" value="0">
            <label class="form-check mb-1"><input class="form-check-input" type="checkbox" name="active" value="1" @checked($val('active'))> <span class="form-check-label">Active — available for orders and transfers</span></label>
          @else<input type="hidden" name="active" value="1">@endif
        </div>
      </div>
    </x-erp.modal>
  @endforeach
  <datalist id="cat-list">@foreach($categories as $c)<option value="{{ $c }}">@endforeach</datalist>
@endif
@endsection
