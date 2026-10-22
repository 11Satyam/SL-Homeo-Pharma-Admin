<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Usage: ->middleware('role:admin,inventory_manager'). No roles = any active user. */
class Role
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();
        if (! $user || $user->status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'Your session has ended.']);
        }
        if ($roles && ! $user->hasRole('admin', ...$roles)) {
            abort(403);
        }

        return $next($request);
    }
}
