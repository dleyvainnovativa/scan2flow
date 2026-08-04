<?php

/*
|==============================================================================
| REFERENCE — merge these bits into your existing bootstrap/app.php
|==============================================================================
| Laravel 13 uses the streamlined bootstrap/app.php (no Kernel.php). Add the
| middleware aliases and, if your install doesn't auto-discover providers,
| register FirebaseServiceProvider. Don't replace your whole file — copy the
| ->withMiddleware() alias block and the provider line.
*/

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\VerifyFirebaseToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'firebase' => VerifyFirebaseToken::class,  // API bearer-token guard
            'admin'    => EnsureUserIsAdmin::class,     // admin-only routes
            'tenant'   => \App\Http\Middleware\ResolveTenant::class,
        ]);

        // ResolveTenant on the web group sets tenant context for requests that
        // DON'T use route-model binding (index/list pages). Requests WITH
        // binding ({id} routes) get context set even earlier — inside the
        // binding callback in RouteBindingServiceProvider — so the binding query
        // is never hidden by the fail-closed scope. Both set the same context.
        $middleware->web(append: [
            \App\Http\Middleware\ResolveTenant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
        $exceptions->render(function (\App\Exceptions\PlanLimitException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 403);
            }
            return back()->with('error', $e->getMessage());
        });
    })
    ->create();

/*
| PROVIDER REGISTRATION
| Laravel 13 auto-discovers providers listed in bootstrap/providers.php.
| Add this line to that array:
|
|     App\Providers\FirebaseServiceProvider::class,
*/
