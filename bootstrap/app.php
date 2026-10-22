<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => \App\Http\Middleware\Role::class, 'field.staff' => \App\Http\Middleware\EnsureFieldStaff::class]);
        $middleware->redirectGuestsTo(fn ($request) => $request->is('api/*') ? null : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API callers always get JSON (401/403/404/422/429), whatever their Accept header.
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            // Don't reveal model class names or whether a record exists in another distributor.
            return $request->is('api/*') ? response()->json(['message' => 'Resource not found.', 'code' => 'not_found'], 404) : null;
        });
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            return $request->is('api/*') ? response()->json(['message' => 'Session expired or invalid. Please log in.', 'code' => 'unauthenticated'], 401) : null;
        });
        // Business-rule violations from services surface as a form error, not a 500.
        $exceptions->render(function (\RuntimeException $e, $request) {
            // Database errors are also RuntimeExceptions; never echo SQL to the client.
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface || $e instanceof \Illuminate\Database\QueryException) {
                return null;
            }
            return $request->is('api/*') || $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withInput()->withErrors(['action' => $e->getMessage()]);
        });
    })->create();
