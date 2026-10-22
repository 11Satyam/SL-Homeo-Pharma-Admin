@extends('erp.auth.shell')
@section('title', 'Welcome back')
@section('lead', 'Sign in to your SL Distribution account.')
@section('content')
<form method="post" action="{{ url('/login') }}">@csrf
  <div class="mb-3"><label class="form-label" for="email">Email</label>
    <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="you@company.com"></div>
  <div class="mb-3"><div class="d-flex justify-content-between"><label class="form-label" for="password">Password</label><a class="small" href="{{ route('password.request') }}">Forgot password?</a></div>
    <div class="input-group"><input id="password" name="password" type="password" class="form-control" required autocomplete="current-password">
    <button type="button" class="btn btn-light" aria-controls="password" onclick="const p=document.getElementById('password');p.type=p.type==='password'?'text':'password';this.textContent=p.type==='password'?'Show':'Hide'">Show</button></div></div>
  <label class="form-check mb-4"><input class="form-check-input" type="checkbox" name="remember"> <span class="form-check-label small">Keep me signed in on this device</span></label>
  <button type="submit" class="btn btn-primary btn-lg w-100 justify-content-center">Sign in</button>
</form>
@endsection
