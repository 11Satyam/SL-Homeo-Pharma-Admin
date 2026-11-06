@extends('erp.layout')
@section('title', $title)
@section('crumb')<span>Reports</span>@endsection
@section('subtitle'){{ $range ? $range.' · ' : '' }}Generated {{ now()->format('d M Y, H:i') }} by {{ auth()->user()->name }}@endsection
@section('actions')
  <button type="button" class="btn btn-primary" onclick="window.print()"><x-icon name="printer" size="sm"/>Print / Save as PDF</button>
@endsection
@section('content')
<section class="panel print-report">
  <div class="table-wrap"><table class="table">
    <thead><tr>@foreach($headers as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse($rows as $r)
      <tr>@foreach($r as $c)<td class="{{ is_int($c) ? 'text-end num' : '' }}">{{ $c }}</td>@endforeach</tr>
    @empty
      <tr><td colspan="{{ count($headers) }}" class="text-muted">Nothing to report.</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
@endsection
