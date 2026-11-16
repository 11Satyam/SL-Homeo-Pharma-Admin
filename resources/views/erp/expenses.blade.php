@extends('erp.layout')
@use('App\Support\Fmt')
@section('title', 'Staff Expenses')
@section('crumb')<span>Field team</span>@endsection
@section('subtitle', 'Claims submitted from the field app. Approved and rejected claims are final.')
@php($canReview = auth()->user()->hasRole('admin', 'distributor', 'accounts_manager'))
@php($s = fn ($k, $f) => (float) ($summary[$k]->$f ?? 0))
@section('actions')
  @if(auth()->user()->hasRole('admin'))<button type="button" class="btn btn-light" data-open="expense-categories"><x-icon name="filter" size="sm"/>Expense categories</button>@endif
@endsection
@section('content')
<div class="row g-3 mb-3">
  <div class="col-sm-4"><x-erp.stat label="Awaiting review" :value="Fmt::money($s('submitted', 'total'), 0)" icon="clock" tone="warn" :meta="(int) $s('submitted', 'n').' claims'" :href="route('erp.expenses', ['status' => 'submitted'])"/></div>
  <div class="col-sm-4"><x-erp.stat label="Approved" :value="Fmt::money($s('approved', 'total'), 0)" icon="check-circle" tone="ok" :meta="(int) $s('approved', 'n').' claims'" :href="route('erp.expenses', ['status' => 'approved'])"/></div>
  <div class="col-sm-4"><x-erp.stat label="Rejected" :value="Fmt::money($s('rejected', 'total'), 0)" icon="x-circle" tone="bad" :meta="(int) $s('rejected', 'n').' claims'" :href="route('erp.expenses', ['status' => 'rejected'])"/></div>
</div>
<section class="panel">
  <div class="toolbar">
    <div class="seg">
      @foreach(['' => 'All', 'submitted' => 'To review', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $l)
        <a href="{{ route('erp.expenses', $k ? ['status' => $k] : []) }}" class="{{ request('status', '') === $k ? 'on' : '' }}">{{ $l }}@if($k)<span class="n">{{ (int) $s($k, 'n') }}</span>@endif</a>
      @endforeach
    </div>
    <div class="search-input grow"><x-icon name="search" size="sm"/><input class="form-control" placeholder="Filter by staff, category or description…" data-table-search="#exp-table" aria-label="Filter expenses"></div>
  </div>
  @if($expenses->count())
  <div class="table-wrap"><table class="table table-hover" id="exp-table">
    <thead><tr><th>Date</th><th>Staff</th><th>Category</th><th>Description</th><th class="text-end">Amount</th><th>Receipt</th><th>Status</th><th><span class="sr-only">Review</span></th></tr></thead>
    <tbody>
    @foreach($expenses as $e)
      <tr>
        <td class="text-nowrap">{{ $e->expense_date->format('d M Y') }}<span class="cell-sub">Submitted {{ $e->created_at->diffForHumans() }}</span></td>
        <td><span class="d-flex align-items-center gap-2"><span class="avatar sm" style="background: {{ Fmt::avatarColor($e->user->name) }}">{{ Fmt::initials($e->user->name) }}</span><span>{{ $e->user->name }}<span class="cell-sub">{{ $e->distributor->business_name }}</span></span></span></td>
        <td><span class="tag">{{ $catNames[$e->category] ?? Fmt::label($e->category) }}</span></td>
        <td style="max-width:280px">{{ $e->description ?: '—' }}</td>
        <td class="text-end num fw-semibold">{{ Fmt::money($e->amount) }}</td>
        <td>@if($e->receipt_path)<a href="{{ route('erp.expenses.receipt', $e) }}" class="d-inline-flex align-items-center gap-1" data-no-load><x-icon name="eye" size="sm"/>View</a>@else<span class="text-muted small">None</span>@endif</td>
        <td><x-erp.status :value="$e->status"/>@if($e->review_note)<span class="cell-sub" title="{{ $e->review_note }}">{{ Str::limit($e->review_note, 36) }}@if($e->reviewer) — {{ $e->reviewer->name }}@endif</span>@endif</td>
        <td class="actions">@if($canReview && $e->status === 'submitted' && $e->user_id !== auth()->id())
          <button type="button" class="btn btn-sm btn-light" data-open="rev-exp-{{ $e->id }}">Review</button>
        @endif</td>
      </tr>
    @endforeach
    <tr class="no-results"><td colspan="8"><x-erp.empty icon="search" title="No expenses match"/></td></tr>
    </tbody>
  </table></div>
  <div class="pager">{{ $expenses->links() }}</div>
  @else
    <x-erp.empty icon="receipt" :title="request('status') ? 'Nothing here' : 'No expense claims yet'" text="Field staff submit expenses from the mobile app; they appear here for review."/>
  @endif
</section>

@if($canReview)
  @foreach($expenses->where('status', 'submitted') as $e)
    @continue($e->user_id === auth()->id())
    <dialog id="rev-exp-{{ $e->id }}" class="modal-x" aria-labelledby="rev-exp-{{ $e->id }}-t" @if(old('_form') === 'rev-exp-'.$e->id) data-autoshow @endif>
      <form method="post" action="{{ route('erp.expenses.review', $e) }}">@csrf<input type="hidden" name="_form" value="rev-exp-{{ $e->id }}">
        <div class="dlg-head"><div><h2 id="rev-exp-{{ $e->id }}-t">Review expense claim</h2><p>{{ $e->user->name }} · {{ $e->expense_date->format('d M Y') }}</p></div><button type="button" class="icon-btn" data-close aria-label="Close"><x-icon name="x"/></button></div>
        <div class="dlg-body">
          <div class="d-flex justify-content-between align-items-start mb-3 p-3" style="background:var(--surface-2);border-radius:10px;border:1px solid var(--border)">
            <dl class="kv mb-0"><dt>Category</dt><dd>{{ $catNames[$e->category] ?? Fmt::label($e->category) }}</dd><dt>Description</dt><dd class="fw-normal">{{ $e->description ?: '—' }}</dd><dt>Receipt</dt><dd>@if($e->receipt_path)<a href="{{ route('erp.expenses.receipt', $e) }}" data-no-load>Download receipt</a>@else<span class="text-muted fw-normal">Not attached</span>@endif</dd></dl>
            <div class="text-end"><div class="text-muted small">Amount</div><div class="fs-4 fw-bold">{{ Fmt::money($e->amount) }}</div></div>
          </div>
          <x-erp.field :form="'rev-exp-'.$e->id" name="note" label="Note to staff" maxlength="500" placeholder="Required when rejecting, e.g. “No receipt for fuel above ₹500”"/>
        </div>
        <div class="dlg-foot">
          <button type="button" class="btn btn-light me-auto" data-close>Cancel</button>
          <button name="decision" value="rejected" class="btn btn-outline-danger"><x-icon name="x" size="sm"/>Reject</button>
          <button name="decision" value="approved" class="btn btn-success"><x-icon name="check" size="sm"/>Approve</button>
        </div>
      </form>
    </dialog>
  @endforeach
@endif

@if(auth()->user()->hasRole('admin'))
  @php($ecForm = old('_form') === 'expense-categories')
  @php($ov = fn ($id, $k, $d) => $ecForm && (string) old('_cat') === (string) $id ? old($k) : $d)
  <dialog id="expense-categories" class="modal-x wide" aria-labelledby="expense-categories-t" @if($ecForm) data-autoshow @endif>
    <div class="dlg-head">
      <div><h2 id="expense-categories-t">Expense categories</h2><p>What field staff choose when they claim an expense in the app. Changes show in the app the next time it loads.</p></div>
      <button type="button" class="icon-btn" data-close aria-label="Close"><x-icon name="x"/></button>
    </div>
    <div class="dlg-body">
      @if($ecForm && $errors->any())
        <div class="alert alert-danger mb-3" role="alert"><x-icon name="alert" size="sm"/><div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div></div>
      @endif

      <form method="post" action="{{ route('erp.expense-categories.store') }}" class="row g-2 align-items-end mb-3 pb-3" style="border-bottom:1px solid var(--border)">@csrf
        <input type="hidden" name="_form" value="expense-categories"><input type="hidden" name="_cat" value="new">
        <div class="col-md-5"><label class="form-label" for="ec-new-name">New category<span class="req" aria-hidden="true">*</span></label>
          <input id="ec-new-name" name="name" class="form-control" required maxlength="100" placeholder="e.g. Doctor samples" value="{{ $ov('new', 'name', '') }}"></div>
        <div class="col-md-3"><label class="form-label" for="ec-new-max">Max per claim (₹)</label>
          <input id="ec-new-max" name="max_amount" type="number" min="1" step="0.01" class="form-control" placeholder="No limit" value="{{ $ov('new', 'max_amount', '') }}"></div>
        <div class="col-md-2"><input type="hidden" name="receipt_required" value="0">
          <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="receipt_required" value="1" @checked($ov('new', 'receipt_required', false))> <span class="form-check-label">Receipt needed</span></label></div>
        <div class="col-md-2"><button class="btn btn-primary w-100"><x-icon name="plus" size="sm"/>Add</button></div>
      </form>

      <div class="table-wrap"><table class="table mb-0">
        <thead><tr><th>Name</th><th style="width:150px">Max per claim (₹)</th><th class="text-center">Receipt needed</th><th class="text-center">On</th><th class="text-end">Used</th><th><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
        @foreach($expenseCategories as $c)
          <tr @class(['text-muted' => ! $c->active])>
            <td>
              <form method="post" action="{{ route('erp.expense-categories.update', $c) }}" id="ec-{{ $c->id }}">@csrf
                <input type="hidden" name="_form" value="expense-categories"><input type="hidden" name="_cat" value="{{ $c->id }}">
                <input type="hidden" name="receipt_required" value="0"><input type="hidden" name="active" value="{{ $c->code === 'other' ? 1 : 0 }}">
              </form>
              <input form="ec-{{ $c->id }}" name="name" class="form-control form-control-sm" required maxlength="100" aria-label="Name of {{ $c->name }}" value="{{ $ov($c->id, 'name', $c->name) }}">
              <span class="cell-sub mono">{{ $c->code }}@if($c->code === 'other') · description required @endif</span>
            </td>
            <td><input form="ec-{{ $c->id }}" name="max_amount" type="number" min="1" step="0.01" class="form-control form-control-sm" placeholder="No limit" aria-label="Max per claim for {{ $c->name }}" value="{{ $ov($c->id, 'max_amount', $c->max_amount !== null ? (float) $c->max_amount : '') }}"></td>
            <td class="text-center"><input form="ec-{{ $c->id }}" class="form-check-input" type="checkbox" name="receipt_required" value="1" aria-label="Receipt needed for {{ $c->name }}" @checked($ov($c->id, 'receipt_required', $c->receipt_required))></td>
            <td class="text-center">@if($c->code === 'other')<span class="text-muted small" data-tip="Always on|Staff need it for anything that fits no category">Always</span>
              @else<input form="ec-{{ $c->id }}" class="form-check-input" type="checkbox" name="active" value="1" aria-label="{{ $c->name }} shown in app" @checked($ov($c->id, 'active', $c->active))>@endif</td>
            <td class="text-end num">{{ $c->used }}</td>
            <td class="actions text-nowrap">
              <button form="ec-{{ $c->id }}" class="btn btn-sm btn-light">Save</button>
              @if($c->code !== 'other')
              <form method="post" action="{{ route('erp.expense-categories.delete', $c) }}" class="d-inline" data-confirm="Delete expense category &quot;{{ $c->name }}&quot;?" data-confirm-ok="Delete" data-tone="danger">@csrf
                <input type="hidden" name="_form" value="expense-categories">
                <button class="btn btn-sm btn-outline-danger" @disabled($c->used > 0) @if($c->used) data-tip="Used by {{ $c->used }} expense(s)|Turn it off instead, so old claims keep their category" @endif>Delete</button>
              </form>
              @endif
            </td>
          </tr>
        @endforeach
        </tbody>
      </table></div>
      <p class="form-text mb-0 mt-2">A category that is turned off disappears from the app, but past claims keep it. Every claim is still limited to ₹{{ number_format(config('fieldapp.expenses.max_amount')) }}.</p>
    </div>
    <div class="dlg-foot"><button type="button" class="btn btn-light" data-close>Close</button></div>
  </dialog>
@endif
@endsection
