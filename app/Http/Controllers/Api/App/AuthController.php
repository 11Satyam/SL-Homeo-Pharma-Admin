<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Middleware\EnsureFieldStaff;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\BusinessDay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/** Splash/session and login screens. Bearer tokens via Sanctum; passwords checked with the users' bcrypt hashes. */
class AuthController extends FieldController
{
    public const PERMISSIONS = [
        'dashboard.view', 'parties.view', 'products.view', 'stock.view_own_distributor',
        'orders.create', 'orders.view_own', 'collections.create', 'collections.view_own',
        'expenses.create', 'expenses.view_own', 'attendance.mark', 'attendance.view_own',
    ];

    public function login(Request $request)
    {
        // Sign in with mobile number + password (preferred for field staff) or email + password.
        $d = $request->validate([
            'mobile' => 'required_without:email|nullable|string|max:20',
            'email' => 'required_without:mobile|nullable|email|max:255',
            'password' => 'required|string|max:255',
            'device_name' => 'nullable|string|max:100',
        ], ['mobile.required_without' => 'Enter your mobile number.', 'email.required_without' => 'Enter your mobile number.']);
        $phone = filled($d['mobile'] ?? null) ? User::normalizePhone($d['mobile']) : null;
        if (filled($d['mobile'] ?? null) && ! $phone) {
            throw \Illuminate\Validation\ValidationException::withMessages(['mobile' => 'Enter a valid 10-digit mobile number.']);
        }
        $ident = $phone ?? Str::lower($d['email']);
        // Same limiter key as the web login, so attempts are counted across both.
        $key = 'login:'.$ident.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $wait = RateLimiter::availableIn($key);

            return response()->json(['message' => "Too many attempts. Try again in {$wait} seconds.", 'code' => 'too_many_attempts', 'retry_after' => $wait], 429)
                ->header('Retry-After', (string) $wait);
        }

        $user = $phone ? User::where('phone', $phone)->first() : User::where('email', $d['email'])->first();
        if (! $user || ! Hash::check($d['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            AuditLog::record('auth.app_failed', null, $phone ? ['mobile' => $phone] : ['email' => $d['email']]);

            return response()->json(['message' => $phone ? 'Invalid mobile number or password.' : 'Invalid email or password.', 'code' => 'invalid_credentials'], 401);
        }
        RateLimiter::clear($key);

        if (! EnsureFieldStaff::allowed($user)) {
            AuditLog::record('auth.app_denied', $user, ['role' => $user->role, 'status' => $user->status]);
            $roleOk = $user->hasRole(...config('fieldapp.roles'));

            return response()->json($roleOk
                ? ['message' => 'Your account or distributor is not active. Contact your distributor.', 'code' => 'account_inactive']
                : ['message' => 'This account cannot use the field staff app.', 'code' => 'role_not_permitted'], 403);
        }

        $token = $user->createToken($d['device_name'] ?? 'mobile', [EnsureFieldStaff::ABILITY], now()->addMinutes(config('fieldapp.token_ttl_minutes')));
        auth()->setUser($user);
        AuditLog::record('auth.app_login', $user, ['device' => $d['device_name'] ?? null]);

        return response()->json(['data' => $this->tokenPayload($token) + $this->profile($user)]);
    }

    /** Splash screen: a 200 means go to Dashboard; 401/403 means go to Login. */
    public function session(Request $request)
    {
        return response()->json(['data' => [
            'token_expires_at' => BusinessDay::iso($request->user()->currentAccessToken()->expires_at),
        ] + $this->profile($request->user())]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        AuditLog::record('auth.app_logout', $request->user());

        return response()->json(['message' => 'Logged out.']);
    }

    /** Rotate: issue a fresh token and revoke the presented one. The presented token must still be valid. */
    public function refresh(Request $request)
    {
        $user = $request->user();
        $old = $user->currentAccessToken();
        $token = $user->createToken($old->name, [EnsureFieldStaff::ABILITY], now()->addMinutes(config('fieldapp.token_ttl_minutes')));
        $old->delete();
        AuditLog::record('auth.app_refresh', $user);

        return response()->json(['data' => $this->tokenPayload($token)]);
    }

    /** Emails the standard reset link (completed on the web reset page). Response never reveals whether the account exists. */
    public function forgotPassword(Request $request)
    {
        // Mobile number or email. With a mobile number the link goes to the account's registered email
        // (no SMS gateway is configured). The reply is identical either way, so it never reveals whether an account exists.
        $d = $request->validate([
            'mobile' => 'required_without:email|nullable|string|max:20',
            'email' => 'required_without:mobile|nullable|email|max:255',
        ], ['mobile.required_without' => 'Enter your mobile number.', 'email.required_without' => 'Enter your mobile number.']);
        if (filled($d['mobile'] ?? null)) {
            $phone = User::normalizePhone($d['mobile']);
            if (! $phone) {
                throw \Illuminate\Validation\ValidationException::withMessages(['mobile' => 'Enter a valid 10-digit mobile number.']);
            }
            $email = User::where('phone', $phone)->value('email');
        } else {
            $email = $d['email'];
        }
        if ($email) {
            Password::sendResetLink(['email' => $email]);
        }

        return response()->json(['message' => 'If that account exists, a password reset link has been sent to its registered email.']);
    }

    private function tokenPayload($token): array
    {
        return ['token' => $token->plainTextToken, 'token_type' => 'Bearer', 'expires_at' => BusinessDay::iso($token->accessToken->expires_at)];
    }

    private function profile(User $u): array
    {
        $dist = $u->distributor;

        return [
            'user' => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'mobile' => $u->phone, 'role' => $u->role, 'status' => $u->status],
            'distributor' => ['id' => $dist->id, 'name' => $dist->business_name, 'area' => $dist->area, 'phone' => $dist->phone, 'status' => $dist->status],
            'permissions' => self::PERMISSIONS,
            'config' => [
                'timezone' => BusinessDay::tz(),
                'payment_methods' => Payment::METHODS,
                'reference_required_methods' => config('fieldapp.collections.reference_required'),
                'expense_categories' => config('fieldapp.expenses.categories'),
                'require_check_in' => (bool) config('fieldapp.attendance.require_check_in'),
                'geofence' => app(AttendanceService::class)->geofence($dist),
            ],
        ];
    }
}
