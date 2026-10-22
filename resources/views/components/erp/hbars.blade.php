{{-- Horizontal bar list. $rows: [[label, value, display?, tone?, href?], ...]; value drives length, display is the printed figure. --}}
@props(['rows' => [], 'max' => null])
@php($top = $max ?? max(1, ...array_map(fn ($r) => (float) $r[1], $rows ?: [[0, 0]])))
<div class="hbars">
  @foreach($rows as $r)
    @php($pct = $top > 0 ? min(100, round((float) $r[1] / $top * 100, 1)) : 0)
    <div class="hbar" data-tip="{{ $r[0] }}|{{ $r[2] ?? $r[1] }}">
      <div class="row1"><span>@if(! empty($r[4]))<a href="{{ $r[4] }}">{{ $r[0] }}</a>@else{{ $r[0] }}@endif</span><span>{{ $r[2] ?? $r[1] }}</span></div>
      <div class="track"><div class="fill {{ $r[3] ?? '' }}" style="width: {{ $pct }}%"></div></div>
    </div>
  @endforeach
</div>
