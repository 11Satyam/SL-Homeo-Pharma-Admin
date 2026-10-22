@extends('erp.auth.shell')
@section('title', 'Set a new password')
@section('lead', 'Use at least 10 characters.')
@section('content')
<form method="post" action="{{ route('password.update') }}">@csrf
  <input type="hidden" name="token" value="{{ $token }}">
  <label class="form-label" for="email">Email</label>
  <input id="email" name="email" type="email" class="form-control mb-3" value="{{ old('email', $email) }}" required>
  <label class="form-label" for="password">New password</label>
  <input id="password" name="password" type="password" class="form-control mb-3" required minlength="10" autocomplete="new-password">
  <label class="form-label" for="password_confirmation">Confirm password</label>
  <input id="password_confirmation" name="password_confirmation" type="password" class="form-control mb-4" required minlength="10" autocomplete="new-password">
  <button type="submit" class="btn btn-primary btn-lg w-100 justify-content-center">Set password</button>
</form>
@endsection
