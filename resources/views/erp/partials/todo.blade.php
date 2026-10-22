{{-- Dashboard guidance: setup checklist (until complete), "To do now" and quick actions. Data from App\Support\Guide. --}}
@php($gu = auth()->user())
@php($checklist = \App\Support\Guide::checklist($gu))
@php($doneCount = collect($checklist)->where(1, true)->count())
@php($tasks = \App\Support\Guide::tasks($gu))

@if($checklist && $doneCount < count($checklist))
@php($nextStep = collect($checklist)->firstWhere(1, false))
<section class="panel mb-3" aria-labelledby="setup-t">
  <div class="panel-head">
    <div><h2 id="setup-t">Getting started: {{ $doneCount }} of {{ count($checklist) }} done</h2>
      <p class="hint">Follow these steps once to set the system up. Each step unlocks the next.</p></div>
    <a href="{{ $nextStep[2] }}" class="btn btn-sm btn-primary">Next: {{ $nextStep[0] }} <x-icon name="arrow-right" size="sm"/></a>
  </div>
  <div class="panel-body">
    <div class="meter mb-3" role="progressbar" aria-valuenow="{{ $doneCount }}" aria-valuemin="0" aria-valuemax="{{ count($checklist) }}" style="height:6px;background:var(--surface-3);border-radius:99px">
      <div style="width:{{ round($doneCount / count($checklist) * 100) }}%;height:100%;background:var(--brand);border-radius:99px"></div></div>
    <ol class="row g-2 list-unstyled mb-0">
      @foreach($checklist as $i => [$label, $done, $url])
        <li class="col-md-6"><a href="{{ $url }}" class="d-flex gap-2 align-items-center {{ $done ? 'text-muted' : '' }}" @if($done) style="text-decoration:line-through" @endif>
          <x-icon :name="$done ? 'check-circle' : 'chev-right'" size="sm"/>{{ $i + 1 }}. {{ $label }}</a></li>
      @endforeach
    </ol>
  </div>
</section>
@endif

<section class="panel mb-3" aria-labelledby="todo-t">
  <div class="panel-head"><div><h2 id="todo-t">To do now</h2><p class="hint">Work waiting for you, most urgent first.</p></div></div>
  @if($tasks)
    <ul class="list-unstyled mb-0">
      @foreach($tasks as $t)
        <li class="d-flex flex-wrap gap-2 align-items-center justify-content-between px-3 py-2" style="border-bottom:1px solid var(--border)">
          <div><b>{{ $t['n'] }} {{ $t['title'] }}</b><div class="small text-muted">{{ $t['why'] }}</div></div>
          <a href="{{ $t['url'] }}" class="btn btn-sm btn-outline-primary">{{ $t['button'] }} <x-icon name="arrow-right" size="sm"/></a>
        </li>
      @endforeach
    </ul>
  @else
    <x-erp.empty icon="check-circle" title="All caught up" text="Nothing is waiting for you. Use the quick actions below to start new work."/>
  @endif
  <div class="panel-foot"><div class="d-flex flex-wrap gap-2">
    @foreach(\App\Support\Guide::actions($gu) as [$label, $url, $primary])
      <a href="{{ $url }}" class="btn btn-sm {{ $primary ? 'btn-primary' : 'btn-light' }}">{{ $label }}</a>
    @endforeach
  </div></div>
</section>
