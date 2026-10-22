@props(['icon' => 'inbox', 'title', 'text' => null])
<div {{ $attributes->merge(['class' => 'empty']) }}>
  <div class="em-ic"><x-icon :name="$icon" size="lg"/></div>
  <h3>{{ $title }}</h3>
  @if($text)<p>{{ $text }}</p>@endif
  @if(trim($slot))<div class="em-cta">{{ $slot }}</div>@endif
</div>
