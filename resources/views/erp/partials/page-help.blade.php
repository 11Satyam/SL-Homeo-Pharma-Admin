{{-- "How this page works": collapsible, per-route guidance from App\Support\Help. Open by default until the user closes it once. --}}
@php($h = \App\Support\Help::page(request()->route()?->getName()))
@if($h && request()->route()->getName() !== 'erp.help')
@php($key = 'help-'.request()->route()->getName())
<details class="panel page-help mb-3" data-help-key="{{ $key }}">
  <summary class="panel-head" style="cursor:pointer;list-style:none">
    <span class="d-inline-flex align-items-center gap-2"><x-icon name="info" size="sm"/><b>How this page works</b>
      <span class="text-muted small d-none d-md-inline">{{ \Illuminate\Support\Str::limit($h['what'], 90) }}</span></span>
    <span class="small text-muted">Show / hide <x-icon name="chev-down" size="sm"/></span>
  </summary>
  <div class="panel-body">
    <p class="mb-2">{{ $h['what'] }}</p>
    @if($h['steps'])
      <ol class="mb-2 ps-3">@foreach($h['steps'] as $s)<li>{{ $s }}</li>@endforeach</ol>
    @endif
    @isset($h['example'])
      <div class="callout mb-2"><x-icon name="pill" size="sm"/><div><b>Example:</b> {{ $h['example'] }}</div></div>
    @endisset
    <div class="d-flex flex-wrap gap-2 align-items-center small">
      @isset($h['next'])@if(Route::has($h['next'][1]))<span class="text-muted">Next step:</span>
        <a href="{{ route($h['next'][1]) }}" class="btn btn-sm btn-outline-primary">{{ $h['next'][0] }} <x-icon name="arrow-right" size="sm"/></a>@endif @endisset
      <a href="{{ route('erp.help') }}#page-{{ \Illuminate\Support\Str::slug($h['title']) }}" class="ms-auto">More help &amp; FAQ</a>
    </div>
  </div>
</details>
<script>
(function(){var d=document.querySelector('details[data-help-key="{{ $key }}"]');if(!d)return;
 try{d.open=localStorage.getItem(d.dataset.helpKey)!=='closed';}catch(e){d.open=true;}
 d.addEventListener('toggle',function(){try{localStorage.setItem(d.dataset.helpKey,d.open?'open':'closed');}catch(e){}});})();
</script>
@endif
