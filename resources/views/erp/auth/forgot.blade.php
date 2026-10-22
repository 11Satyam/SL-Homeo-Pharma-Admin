@extends('erp.auth.shell')
@section('title', 'Reset your password')
@section('lead', 'Enter your account email and we’ll send a secure reset link.')
@section('content')
<form method="post" action="{{ route('password.email') }}">@csrf
  <label class="form-label" for="email">Account email</label>
  <input id="email" name="email" type="email" class="form-control mb-4" required autofocus placeholder="you@company.com">
  <button type="submit" class="btn btn-primary btn-lg w-100 justify-content-center">Send reset link</button>
  <a href="{{ route('login') }}" class="d-block text-center small mt-3">Back to sign in</a>
</form>
@endsection
