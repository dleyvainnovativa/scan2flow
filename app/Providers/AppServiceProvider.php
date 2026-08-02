<?php

/*
|==============================================================================
| REFERENCE — policy registration
|==============================================================================
| Laravel 13 auto-discovers policies when they follow the naming convention
| (App\Models\Area -> App\Policies\AreaPolicy), which these do. So NO manual
| registration is strictly required.
|
| If you prefer explicit registration (or your models live outside App\Models),
| add a Gate::policy() block in App\Providers\AppServiceProvider::boot():
*/

namespace App\Providers;

use App\Models\Area;
use App\Models\Document;
use App\Models\Template;
use App\Policies\AreaPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\TemplatePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Optional — only if you disable auto-discovery.
        Gate::policy(Area::class, AreaPolicy::class);
        Gate::policy(Template::class, TemplatePolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
    }
}
