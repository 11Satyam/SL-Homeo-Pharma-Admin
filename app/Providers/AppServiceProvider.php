<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Pagination\Paginator::useBootstrapFive();

        // Admin layout: sidebar badges + notification menu, computed once per page from live, scoped records.
        View::composer('erp.layout', function ($view) {
            if ($u = auth()->user()) {
                $view->with('navAlerts', \App\Support\AdminNav::alerts($u));
            }
        });

        // Field app: per-IP login limit (on top of the per-account lockout), per-user read/write limits.
        RateLimiter::for('app-login', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));
        RateLimiter::for('app', fn (Request $r) => Limit::perMinute(120)->by('app:'.($r->user()?->id ?: $r->ip())));
        RateLimiter::for('app-write', fn (Request $r) => Limit::perMinute(30)->by('app-write:'.($r->user()?->id ?: $r->ip())));
    }
}
