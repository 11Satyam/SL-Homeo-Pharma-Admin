@extends('erp.layout')
@use('App\Support\Fmt')
@section('title', 'Audit Logs')
@section('crumb')<span>Administration</span>@endsection
@section('subtitle', 'An immutable record of sign-ins, approvals, stock postings and financial actions.')
@php($tone = fn ($a) => str_contains($a, 'failed') || str_contains($a, 'denied') || str_contains($a, 'revoked') || str_contains($a, 'suspended') || str_contains($a, 'rejected') ? 'bad' : (str_starts_with($a, 'auth.') ? 'neutral' : (str_starts_with($a, 'stock.') || str_starts_with($a, 'transfer.') ? 'violet' : (str_contains($a, 'verified') || str_contains($a, 'approved') || str_contains($a, 'delivered') ? 'ok' : 'info'))))
@section('content')
<section class="panel">
  <form class="toolbar" method="get">
    <div class="seg">
      @foreach(['' => 'All', 'auth' => 'Sign-ins', 'order' => 'Orders', 'payment' => 'Payments', 'stock' => 'Stock', 'transfer' => 'Transfers', 'expense' => 'Expenses', 'user' => 'Users'] as $k => $l)
        <a href="{{ route('erp.audit', $k ? ['action' => $k] : []) }}" class="{{ request('action', '') === $k ? 'on' : '' }}">{{ $l }}</a>
      @endforeach
    </div>
    <div class="search-input grow"><x-icon name="search" size="sm"/><input name="action" value="{{ request('action') }}" class="form-control" placeholder="Action prefix, e.g. order.delivered" aria-label="Filter by action"></div>
    <button class="btn btn-light">Filter</button>
  </form>
  @if($logs->count())
  <div class="table-wrap"><table class="table table-hover">
    <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Record</th><th>Details</th><th>IP</th></tr></thead>
    <tbody>
    @foreach($logs as $l)
      <tr>
        <td class="text-nowrap">{{ $l->created_at->format('d M Y') }}<span class="cell-sub num">{{ $l->created_at->format('H:i:s') }}</span></td>
        <td>@if($l->user)<span class="d-inline-flex align-items-center gap-2"><span class="avatar sm" style="background: {{ Fmt::avatarColor($l->user->name) }}">{{ Fmt::initials($l->user->name) }}</span>{{ $l->user->name }}</span>@else<span class="text-muted">System / guest</span>@endif</td>
        <td><span class="badge-s {{ $tone($l->action) }}"><span class="dot"></span><span class="mono">{{ $l->action }}</span></span></td>
        <td>@if($l->entity)<span class="tag">{{ $l->entity }} #{{ $l->entity_id }}</span>@endif</td>
        <td><div class="json-cell" title="{{ json_encode($l->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}">{{ $l->data ? json_encode($l->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '—' }}</div></td>
        <td class="mono text-muted">{{ $l->ip }}</td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
  <div class="pager">{{ $logs->links() }}</div>
  @else
    <x-erp.empty icon="shield" title="No entries" text="Nothing matches this filter."/>
  @endif
</section>
@endsection
