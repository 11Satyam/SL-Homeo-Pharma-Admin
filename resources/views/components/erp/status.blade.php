@props(['value', 'label' => null])
@php([$tone, $text] = \App\Support\Fmt::status($value))
<span {{ $attributes->merge(['class' => 'badge-s '.$tone]) }}><span class="dot" aria-hidden="true"></span>{{ $label ?? $text }}</span>
