<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;

/**
 * Runs after auth:sanctum on every field-app route. Re-checks on each request that
 * the token was issued for the field app and that the user, role and distributor are
 * still allowed, so suspending a user or distributor in the Admin Panel takes effect
 * immediately and revokes their tokens.
 */
class EnsureFieldStaff
{
    public const ABILITY = 'field-app';

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user->tokenCan(self::ABILITY)) {
            return response()->json(['message' => 'This token is not valid for the field app.', 'code' => 'invalid_token'], 401);
        }
        if (! self::allowed($user)) {
            $user->tokens()->delete();
            AuditLog::record('auth.app_revoked', $user, ['reason' => 'status_or_role']);

            return response()->json(['message' => 'Your account is not active for the field app. Contact your distributor.', 'code' => 'account_inactive'], 403);
        }

        return $next($request);
    }

    public static function allowed($user): bool
    {
        return $user->status === 'active'
            && $user->hasRole(...config('fieldapp.roles'))
            && $user->distributor_id
            && $user->distributor?->status === 'active';
    }
}
