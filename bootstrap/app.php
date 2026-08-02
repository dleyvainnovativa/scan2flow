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
        // >>> ADD THESE ALIASES <<<
        $middleware->alias([
            'firebase' => VerifyFirebaseToken::class,  // API bearer-token guard
            'admin'    => EnsureUserIsAdmin::class,     // admin-only routes
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();

/*
| PROVIDER REGISTRATION
| Laravel 13 auto-discovers providers listed in bootstrap/providers.php.
| Add this line to that array:
|
|     App\Providers\FirebaseServiceProvider::class,
*/
