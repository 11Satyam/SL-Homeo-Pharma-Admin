@props(['label', 'value', 'icon' => null, 'tone' => '', 'href' => null, 'meta' => null, 'mini' => false])
@php($tag = $href ? 'a' : 'div')
{{-- Colour accent follows the tile's meaning unless the page picked one explicitly. --}}
@php($accent = str_contains((string) $attributes->get('class'), 'c-') ? '' : ' c-'.(['ok' => 'green', 'warn' => 'amber', 'bad' => 'rose', 'violet' => 'violet'][$tone] ?? 'blue'))
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'stat'.($mini ? ' stat-mini' : '').$accent]) }}>
  <div class="label">@if($icon)<span class="tile-ic {{ $tone }}"><x-icon :name="$icon" size="sm"/></span>@endif<span>{{ $label }}</span></div>
  <div class="value">{{ $value }}</div>
  @if($meta || trim($slot))<div class="meta">{{ $meta }}{{ $slot }}</div>@endif
</{{ $tag }}>
