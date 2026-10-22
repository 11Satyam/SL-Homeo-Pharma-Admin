{{-- Labelled input with server-side error state. Pass a slot to render a custom control (select, input-group) instead of the default input.
     `form` scopes old input + errors to one modal when several forms on a page share field names. --}}
@props(['name', 'label', 'type' => 'text', 'required' => false, 'value' => null, 'help' => null, 'placeholder' => null, 'id' => null, 'form' => null])
@php
  $fid = $id ?? 'f-'.\Illuminate\Support\Str::slug($name).'-'.substr(md5(($form ?? '').$name), 0, 6);
  $dot = str_replace(['[', ']'], ['.', ''], $name);
  $mine = $form === null || old('_form') === $form; // this field's form is the one that was just submitted
  $err = $mine ? $errors->first($dot) : null;
  $val = $mine ? old($dot, $value) : $value;
@endphp
<div {{ $attributes->only(['class', 'data-show-if']) }}>
  <label class="form-label" for="{{ $fid }}">{{ $label }}@if($required)<span class="req" aria-hidden="true">*</span>@endif</label>
  @if(trim($slot))
    {{ $slot }}
  @else
    <input id="{{ $fid }}" name="{{ $name }}" type="{{ $type }}" value="{{ $val }}" @if($placeholder) placeholder="{{ $placeholder }}" @endif
      {{ $attributes->except(['class', 'data-show-if'])->merge(['class' => 'form-control'.($err ? ' is-invalid' : '')]) }} @if($required) required @endif
      @if($help) aria-describedby="{{ $fid }}-h" @endif>
  @endif
  @if($err)<div class="invalid-feedback d-block">{{ $err }}</div>@endif
  @if($help)<div class="form-text" id="{{ $fid }}-h">{{ $help }}</div>@endif
</div>
