@props(['name', 'size' => null])
<svg {{ $attributes->merge(['class' => 'ic'.($size ? ' ic-'.$size : '')]) }} aria-hidden="true" focusable="false"><use href="#i-{{ $name }}"/></svg>
