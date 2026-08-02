<?php

namespace App\Providers;

use App\Services\FirebaseService;
use Illuminate\Support\ServiceProvider;

class FirebaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton so the SDK factory only initializes once per request.
        $this->app->singleton(FirebaseService::class, fn () => new FirebaseService());
    }

    public function boot(): void
    {
        //
    }
}
