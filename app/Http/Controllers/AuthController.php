<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('erp.auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $key = 'login:'.Str::lower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.'])->onlyInput('email');
        }

        if (! Auth::attempt($data + ['status' => 'active'], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            AuditLog::record('auth.failed', null, ['email' => $data['email']]);

            return back()->withErrors(['email' => 'Invalid credentials or inactive account.'])->onlyInput('email');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        AuditLog::record('auth.login', $request->user());

        return redirect()->intended(route('erp.dashboard'));
    }

    public function logout(Request $request)
    {
        AuditLog::record('auth.logout', $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showForgot()
    {
        return view('erp.auth.forgot');
    }

    public function sendReset(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        Password::sendResetLink($request->only('email'));

        // Same response whether or not the account exists (no user enumeration).
        return back()->with('status', 'If that account exists, a reset link has been sent.');
    }

    public function showReset(Request $request, string $token)
    {
        return view('erp.auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $request->validate(['token' => 'required', 'email' => 'required|email', 'password' => 'required|min:10|confirmed']);
        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function (User $u, string $pw) {
            // New remember token invalidates "remember me" sessions on other devices.
            $u->forceFill(['password' => Hash::make($pw), 'remember_token' => Str::random(60)])->save();
            AuditLog::record('auth.password_reset', $u);
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }
}
