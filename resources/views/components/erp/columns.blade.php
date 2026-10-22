{{-- Single-series column chart (server-rendered SVG). $data: [label => value]. Hover tooltips via data-tip; data table for screen readers/print. --}}
@props(['data' => [], 'money' => false, 'height' => 220, 'title' => 'Chart', 'highlightLast' => true])
@php
  $fmt = fn ($v) => $money ? \App\Support\Fmt::money($v, 0) : \App\Support\Fmt::num($v);
  $fmtTick = fn ($v) => $money ? \App\Support\Fmt::moneyShort($v) : ($v >= 1000 ? round($v / 1000, 1).'K' : (string) $v);
  $vals = array_values($data); $labels = array_keys($data); $n = max(count($vals), 1);
  $max = max(1, ...($vals ?: [0]));
  $mag = 10 ** floor(log10($max)); $stepNice = collect([1, 2, 2.5, 5, 10])->first(fn ($s) => $s * $mag * 4 >= $max) * $mag;
  $top = $stepNice * 4;
  $W = 640; $H = $height; $padL = 52; $padR = 8; $padT = 14; $padB = 26;
  $plotW = $W - $padL - $padR; $plotH = $H - $padT - $padB; $base = $padT + $plotH;
  $band = $plotW / $n; $bw = min(24, max(4, $band * .62));
  $every = $n > 16 ? 3 : ($n > 9 ? 2 : 1);
  $maxIdx = $vals ? array_search(max($vals), $vals) : -1;
@endphp
<div class="chart" role="img" aria-label="{{ $title }}">
  <svg viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="xMidYMid meet">
    @for($i = 0; $i <= 4; $i++)
      @php $y = $base - $plotH * $i / 4; @endphp
      <line class="{{ $i ? 'gl' : 'bl' }}" x1="{{ $padL }}" x2="{{ $W - $padR }}" y1="{{ $y }}" y2="{{ $y }}"/>
      <text class="tick" x="{{ $padL - 8 }}" y="{{ $y + 4 }}" text-anchor="end">{{ $fmtTick($stepNice * $i) }}</text>
    @endfor
    @foreach($vals as $i => $v)
      @php
        $h = $v > 0 ? max(2, $plotH * $v / $top) : 0; $x = $padL + $band * $i + ($band - $bw) / 2; $y = $base - $h; $r = min(4, $h);
        $last = $highlightLast && $i === $n - 1;
      @endphp
      <g class="col" data-tip="{{ $labels[$i] }}|{{ $fmt($v) }}" tabindex="0" aria-label="{{ $labels[$i] }}: {{ $fmt($v) }}">
        <rect class="hit-bg" x="{{ $padL + $band * $i }}" y="{{ $padT }}" width="{{ $band }}" height="{{ $plotH }}" rx="4"/>
        @if($h > 0)<path class="bar{{ $highlightLast && ! $last ? ' dim' : '' }}" d="M{{ $x }},{{ $base }} V{{ $y + $r }} Q{{ $x }},{{ $y }} {{ $x + $r }},{{ $y }} H{{ $x + $bw - $r }} Q{{ $x + $bw }},{{ $y }} {{ $x + $bw }},{{ $y + $r }} V{{ $base }} Z"/>@endif
        @if($i === $maxIdx && $v > 0)<text class="val" x="{{ $x + $bw / 2 }}" y="{{ $y - 5 }}" text-anchor="middle">{{ $fmtTick($v) }}</text>@endif
      </g>
      @if($i % $every === 0 || $i === $n - 1)
        <text class="tick" x="{{ $padL + $band * $i + $band / 2 }}" y="{{ $H - 6 }}" text-anchor="middle">{{ $labels[$i] }}</text>
      @endif
    @endforeach
  </svg>
  <details class="chart-table no-print"><summary>View as table</summary>
    <table class="table table-sm"><thead><tr><th>Period</th><th class="text-end">Value</th></tr></thead><tbody>
      @foreach($data as $k => $v)<tr><td>{{ $k }}</td><td class="text-end num">{{ $fmt($v) }}</td></tr>@endforeach
    </tbody></table>
  </details>
</div>
