{{-- Native <dialog> wrapping a form. Re-opens itself after a failed submit (old('_form') matches its id) so field errors show in context. --}}
@props(['id', 'title', 'subtitle' => null, 'action' => null, 'size' => '', 'submit' => 'Save', 'submitClass' => 'btn-primary', 'confirm' => null, 'tone' => null])
<dialog id="{{ $id }}" class="modal-x {{ $size }}" aria-labelledby="{{ $id }}-t" @if(old('_form') === $id) data-autoshow @endif>
  <form method="post" action="{{ $action }}" {{ $attributes }} @if($confirm) data-confirm="{{ $confirm }}" @endif @if($tone) data-tone="{{ $tone }}" @endif>
    @csrf<input type="hidden" name="_form" value="{{ $id }}">
    <div class="dlg-head">
      <div><h2 id="{{ $id }}-t">{{ $title }}</h2>@if($subtitle)<p>{{ $subtitle }}</p>@endif</div>
      <button type="button" class="icon-btn" data-close aria-label="Close"><x-icon name="x"/></button>
    </div>
    <div class="dlg-body">
      @if(old('_form') === $id && $errors->any())
        <div class="alert alert-danger mb-3" role="alert"><x-icon name="alert" size="sm"/><div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div></div>
      @endif
      {{ $slot }}
    </div>
    <div class="dlg-foot">
      <button type="button" class="btn btn-light" data-close>Cancel</button>
      <button type="submit" class="btn {{ $submitClass }}">{{ $submit }}</button>
    </div>
  </form>
</dialog>
