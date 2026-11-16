@extends('erp.layout')
@use('App\Support\Fmt')
@section('title', 'Staff Attendance')
@section('crumb')<span>Field team</span>@endsection
@section('subtitle', 'Check-in and check-out times are recorded by the server. Locations are as reported by the staff member’s device and are not independently verified.')
@php($tz = \App\Support\BusinessDay::tz())
@php($outside = $rows->filter(fn ($a) => $a->check_in_within_geofence === false || $a->check_out_within_geofence === false)->count())
@section('content')
<div class="row g-3 mb-3">
  <div class="col-sm-4"><x-erp.stat mini label="Records on this page" :value="$rows->count()" icon="calendar"/></div>
  <div class="col-sm-4"><x-erp.stat mini label="Still checked in" :value="$rows->where('status', 'checked_in')->count()" icon="clock" tone="warn"/></div>
  <div class="col-sm-4"><x-erp.stat mini label="Outside geofence" :value="$outside" icon="pin" :tone="$outside ? 'bad' : 'ok'"/></div>
</div>
<section class="panel">
  <form class="toolbar" method="get">
    <input type="date" name="date" value="{{ request('date') }}" class="form-control" style="max-width:180px" aria-label="Date">
    <select name="user_id" class="form-select" style="max-width:220px" aria-label="Staff"><option value="">All staff</option>@foreach($staff as $s)<option value="{{ $s->id }}" @selected(request('user_id') == $s->id)>{{ $s->name }}</option>@endforeach</select>
    <button class="btn btn-light"><x-icon name="filter" size="sm"/>Apply</button>
    <a href="{{ route('erp.attendance', ['date' => \App\Support\BusinessDay::today()]) }}" class="btn btn-ghost">Today</a>
    @if(request()->hasAny(['date', 'user_id']))<a href="{{ route('erp.attendance') }}" class="btn btn-ghost">Reset</a>@endif
  </form>
  @if($rows->count())
  <div class="table-wrap"><table class="table table-hover">
    <thead><tr><th>Date</th><th>Staff</th><th>Check-in</th><th>Check-out</th><th class="text-end">Hours</th><th>Status</th><th>Remarks</th></tr></thead>
    <tbody>
    @foreach($rows as $a)
      <tr>
        <td class="text-nowrap">{{ $a->date->format('D, d M Y') }}</td>
        <td><span class="d-flex align-items-center gap-2"><span class="avatar sm" style="background: {{ Fmt::avatarColor($a->user->name) }}">{{ Fmt::initials($a->user->name) }}</span><span>{{ $a->user->name }}<span class="cell-sub">{{ $a->distributor->business_name }}</span></span></span></td>
        @foreach(['check_in', 'check_out'] as $p)
          <td>@if($a->{$p.'_at'})
            <b class="num">{{ $a->{$p.'_at'}->setTimezone($tz)->format('H:i') }}</b>
            @php($fence = $a->{$p.'_within_geofence'})
            @if($fence === true)<span class="badge-s ok ms-1"><span class="dot"></span>Inside</span>@elseif($fence === false)<span class="badge-s bad ms-1" data-tip="Outside geofence|{{ Fmt::num($a->{$p.'_distance_m'}) }} m from office"><span class="dot"></span>{{ Fmt::num($a->{$p.'_distance_m'}) }} m out</span>@endif
            @php($coords = round($a->{$p.'_lat'}, 5).', '.round($a->{$p.'_lng'}, 5))
            @php($place = $a->{$p.'_address'})
            <span class="cell-sub" style="max-width:260px;white-space:normal"><a href="https://maps.google.com/?q={{ $a->{$p.'_lat'} }},{{ $a->{$p.'_lng'} }}" target="_blank" rel="noopener" title="{{ $coords }} · open in Google Maps"><x-icon name="pin" size="sm"/>{{ $place ?: $coords }}</a>@if($a->{$p.'_accuracy_m'} !== null) <span class="text-nowrap">±{{ round($a->{$p.'_accuracy_m'}) }} m</span>@endif</span>
          @else<span class="text-muted">—</span>@endif</td>
        @endforeach
        <td class="text-end num">{{ $a->check_out_at ? number_format($a->check_in_at->diffInMinutes($a->check_out_at) / 60, 1) : '—' }}</td>
        <td><x-erp.status :value="$a->status"/></td>
        <td class="text-muted">{{ $a->remarks ?: '' }}</td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
  <div class="pager">{{ $rows->links() }}</div>
  @else
    <x-erp.empty icon="pin" title="No attendance records" :text="request()->hasAny(['date','user_id']) ? 'Nobody checked in for this filter.' : 'Field staff check in and out from the mobile app.'"/>
  @endif
</section>
@endsection
