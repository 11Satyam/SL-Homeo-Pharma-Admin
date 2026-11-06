@extends('erp.layout')
@use('App\Support\Fmt')
@section('title', 'Distributor stock report')
@section('crumb')<a href="{{ route('erp.distributors') }}">Distributors</a>@endsection
@section('subtitle', 'Every distributor side by side. Dates limit allocated, sold, returned, damaged and missing; on hand, available and in transit are always live.')
@php($range = request()->only('from', 'to'))
@section('actions')
  <a class="btn btn-light" data-no-load href="{{ route('erp.distributors.report', $range + ['format' => 'xlsx']) }}"><x-icon name="download" size="sm"/>Export Excel</a>
  <a class="btn btn-light" href="{{ route('erp.distributors.report', $range + ['format' => 'pdf']) }}"><x-icon name="printer" size="sm"/>Export PDF</a>
@endsection
@section('content')
<section class="panel">
  <form class="toolbar" method="get">
    <input type="date" name="from" value="{{ request('from') }}" class="form-control" style="max-width:160px" aria-label="From date">
    <input type="date" name="to" value="{{ request('to') }}" class="form-control" style="max-width:160px" aria-label="To date">
    <button class="btn btn-light"><x-icon name="filter" size="sm"/>Apply</button>
    @if($range)<a href="{{ route('erp.distributors.report') }}" class="btn btn-ghost">Reset</a>@endif
    <div class="search-input grow"><x-icon name="search" size="sm"/><input class="form-control" placeholder="Search distributors…" data-table-search="#dist-report" aria-label="Search distributors"></div>
  </form>
  @if($rows->count())
  <div class="table-wrap"><table class="table table-hover" id="dist-report">
    <thead><tr><th data-sort>Distributor</th><th data-sort>Territory</th><th class="text-end" data-sort="num">Allocated</th><th class="text-end" data-sort="num">On hand</th><th class="text-end" data-sort="num">Available</th><th class="text-end" data-sort="num">Sold</th><th class="text-end" data-sort="num">In transit</th><th class="text-end" data-sort="num">Returned</th><th class="text-end" data-sort="num">Damaged</th><th class="text-end" data-sort="num">Missing</th><th>Status</th></tr></thead>
    <tbody>
    @foreach($rows as $x)@php($t = $x['totals'])
      <tr>
        <td><a href="{{ route('erp.distributors.show', $x['distributor']) }}" class="cell-main">{{ $x['distributor']->business_name }}</a></td>
        <td>{{ $x['distributor']->area ?: '—' }}</td>
        <td class="text-end num">{{ Fmt::num($t['allocated']) }}</td><td class="text-end num">{{ Fmt::num($t['on_hand']) }}</td><td class="text-end num fw-semibold">{{ Fmt::num($t['available']) }}</td>
        <td class="text-end num">{{ Fmt::num($t['sold']) }}</td><td class="text-end num">{{ Fmt::num($t['transit_in']) }}</td><td class="text-end num">{{ Fmt::num($t['returned']) }}</td>
        <td class="text-end num">{{ Fmt::num($t['damaged']) }}</td><td class="text-end num">{{ Fmt::num($t['missing']) }}</td>
        <td><x-erp.status :value="$x['distributor']->status"/></td>
      </tr>
    @endforeach
    <tr class="no-results"><td colspan="11"><x-erp.empty icon="search" title="No distributors match"/></td></tr>
    </tbody>
  </table></div>
  @else
    <x-erp.empty icon="building" title="No distributors yet"/>
  @endif
</section>
@endsection
