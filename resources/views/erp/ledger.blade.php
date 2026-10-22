@extends('erp.layout')
@use('App\Support\Fmt')
@section('title', 'Stock Ledger')
@section('crumb')<span>Inventory</span>@endsection
@section('subtitle', 'Every quantity change, append-only. Corrections are posted as equal-and-opposite reversals — entries are never edited or deleted.')
@php($isAdmin = auth()->user()->hasRole('admin'))
@php($types = ['opening' => 'Opening', 'receipt' => 'Receipt', 'transfer_dispatch' => 'Transfer dispatch', 'transfer_receive' => 'Transfer receipt', 'transfer_damage' => 'Transit damage', 'sale' => 'Sale (delivery)', 'return' => 'Return to stock', 'return_damaged' => 'Return (damaged)', 'damage_adjustment' => 'Write-off', 'reversal' => 'Reversal'])
@php($tone = ['opening' => 'info', 'receipt' => 'info', 'transfer_dispatch' => 'violet', 'transfer_receive' => 'violet', 'sale' => 'ok', 'return' => 'warn', 'return_damaged' => 'bad', 'transfer_damage' => 'bad', 'damage_adjustment' => 'bad', 'reversal' => 'neutral'])
@section('actions')
  <a href="{{ request()->fullUrlWithQuery(['export' => 1]) }}" class="btn btn-light" data-no-load><x-icon name="download" size="sm"/>Export CSV</a>
@endsection
@section('content')
@if($mismatches)
  <div class="alert alert-danger mb-3" role="alert"><x-icon name="alert" size="sm"/><div><b>Ledger / balance mismatch detected.</b> {{ count($mismatches) }} balance(s) differ from the ledger — investigate before posting further movements.
    <div class="mono small mt-1">@foreach($mismatches as $m)Location #{{ $m['location'] }} · product #{{ $m['product'] }}: ledger {{ $m['ledger'] }} vs balance {{ $m['balance'] }}<br>@endforeach</div></div></div>
@elseif(! auth()->user()->scopedDistributorId())
  <div class="alert alert-success mb-3"><x-icon name="check-circle" size="sm"/><div><b>Reconciled.</b> All cached balances match the ledger.</div></div>
@endif

<section class="panel">
  <form class="toolbar" method="get">
    <select name="product_id" class="form-select" style="max-width:280px" aria-label="Product"><option value="">All products</option>@foreach($products as $p)<option value="{{ $p->id }}" @selected(request('product_id') == $p->id)>{{ $p->name }}</option>@endforeach</select>
    <select name="type" class="form-select" style="max-width:220px" aria-label="Movement type"><option value="">All movement types</option>@foreach($types as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach</select>
    <button class="btn btn-light"><x-icon name="filter" size="sm"/>Apply</button>
    @if(request()->hasAny(['product_id', 'type']))<a href="{{ route('erp.ledger') }}" class="btn btn-ghost">Reset</a>@endif
    <span class="ms-auto text-muted small">{{ Fmt::num($movements->total()) }} movements</span>
  </form>
  @if($movements->count())
  <div class="table-wrap"><table class="table table-hover">
    <thead><tr><th>#</th><th>When</th><th>Type</th><th>Product</th><th class="text-end">Qty</th><th>From → To</th><th>Reference</th><th>By</th>@if($isAdmin)<th><span class="sr-only">Actions</span></th>@endif</tr></thead>
    <tbody>
    @foreach($movements as $m)
      <tr>
        <td class="text-muted num">{{ $m->id }}</td>
        <td class="text-nowrap">{{ $m->created_at->format('d M Y') }}<span class="cell-sub">{{ $m->created_at->format('H:i') }}</span></td>
        <td><span class="badge-s {{ $tone[$m->type] ?? 'neutral' }}"><span class="dot"></span>{{ $types[$m->type] ?? Fmt::label($m->type) }}</span></td>
        <td><span class="cell-main">{{ $m->product->name }}</span><span class="cell-sub mono">{{ $m->product->sku }}</span></td>
        <td class="text-end num fw-semibold">{{ Fmt::num($m->qty) }}</td>
        <td class="text-nowrap">{{ $m->from->name }} <x-icon name="arrow-right" size="sm" class="text-muted"/> {{ $m->to->name }}</td>
        <td>@if($m->reference)<span class="mono">{{ $m->reference }}</span>@endif @if($m->reason)<span class="cell-sub">{{ Str::limit($m->reason, 50) }}</span>@endif @if(! $m->reference && ! $m->reason)<span class="text-muted">—</span>@endif</td>
        <td>@if($m->user)<span class="d-inline-flex align-items-center gap-2"><span class="avatar sm" style="background: {{ Fmt::avatarColor($m->user->name) }}">{{ Fmt::initials($m->user->name) }}</span>{{ $m->user->name }}</span>@else<span class="text-muted">System</span>@endif</td>
        @if($isAdmin)
        <td class="actions">@if($m->type !== 'reversal')<button type="button" class="btn btn-sm btn-ghost" data-open="rev-{{ $m->id }}" data-tip="Reverse|Post an equal and opposite entry"><x-icon name="undo" size="sm"/><span class="sr-only">Reverse</span></button>@endif</td>
        @endif
      </tr>
    @endforeach
    </tbody>
  </table></div>
  <div class="pager">{{ $movements->links() }}</div>
  @else
    <x-erp.empty icon="ledger" :title="request()->hasAny(['product_id','type']) ? 'No movements match these filters' : 'The ledger is empty'" text="Receipts, transfers, deliveries, returns and write-offs all post here automatically."/>
  @endif
</section>

@if($isAdmin)
  @foreach($movements as $m)
    @if($m->type !== 'reversal')
      <x-erp.modal :id="'rev-'.$m->id" :title="'Reverse movement #'.$m->id" :subtitle="Fmt::num($m->qty).' × '.$m->product->name.' · '.$m->from->name.' → '.$m->to->name"
        :action="route('erp.ledger.reverse', $m)" submit="Post reversal" submit-class="btn-danger" size="narrow"
        confirm="Post a reversing entry? This moves the stock back and is permanently recorded." tone="danger">
        <x-erp.field :form="'rev-'.$m->id" name="reason" label="Reason for reversal" required maxlength="255" placeholder="Wrong quantity entered on GRN"/>
      </x-erp.modal>
    @endif
  @endforeach
@endif
@endsection
