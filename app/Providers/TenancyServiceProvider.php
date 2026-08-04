<?php

namespace App\Providers;

use App\Support\TenantContext;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One shared TenantContext per request/job lifecycle.
        $this->app->singleton(TenantContext::class, fn() => new TenantContext());
    }

    public function boot(): void
    {
        //
    }
}
