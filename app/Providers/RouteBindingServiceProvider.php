<?php

namespace App\Providers;

use App\Models\Area;
use App\Models\Document;
use App\Models\Template;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Explicit route-model binding that resolves tenant-owned models WITHOUT relying
 * on middleware ordering.
 *
 * The problem: Laravel's automatic route-model binding (SubstituteBindings) runs
 * inside the web group, and can execute BEFORE the ResolveTenant middleware sets
 * the tenant context. Under the fail-closed global scope, a binding query with
 * no context returns nothing -> every /templates/{id}, /areas/{id} 404s.
 *
 * The fix: define the bindings ourselves. We set tenant context from the authed
 * user (available at binding time), resolve the model, and enforce tenant
 * ownership manually. Correct 404s for real missing models AND correct isolation
 * (another tenant's model is treated as not found).
 *
 * Platform admins (no tenant context) can resolve any tenant's model - intended,
 * since their controllers already operate cross-tenant via withoutScope().
 */
class RouteBindingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Scoped models: resolved with the scope bypassed, ownership enforced.
        $this->bindTenantModel('template', Template::class);
        $this->bindTenantModel('area', Area::class);
        $this->bindTenantModel('document', Document::class);

        // User is NOT globally scoped (auth must load it without context), so it
        // gets its own binding - registered ONCE here, not inside the loop above.
        $this->bindUser();
    }

    /**
     * Ensure tenant context is set from the authenticated user. Binding can run
     * before the ResolveTenant middleware, but the session has already
     * authenticated the user by now, so auth()->user() works. Setting it here
     * makes binding independent of middleware order and primes the context for
     * the controller that follows.
     */
    private function primeContext(TenantContext $ctx): void
    {
        if (! $ctx->has() && ! $ctx->isBypassed()) {
            $user = auth()->user();
            if ($user && $user->tenant_id !== null) {
                $ctx->set($user->tenant_id);
            }
        }
    }

    /**
     * Bind {param} to a scoped tenant-owned model: resolve without the scope
     * (so timing can't hide it), then enforce tenant ownership explicitly.
     */
    private function bindTenantModel(string $param, string $modelClass): void
    {
        Route::bind($param, function ($value) use ($modelClass) {
            $ctx = app(TenantContext::class);
            $this->primeContext($ctx);

            $model = $ctx->withoutScope(
                fn() =>
                $modelClass::where((new $modelClass)->getRouteKeyName(), $value)->first()
            );

            if (! $model) {
                throw new NotFoundHttpException();
            }

            if ($ctx->has() && (int) $model->tenant_id !== (int) $ctx->id()) {
                throw new NotFoundHttpException(); // another tenant -> not found (no leak)
            }

            return $model;
        });
    }

    /**
     * Bind {user}. User isn't globally scoped, so find directly (no
     * withoutScope needed), then enforce tenant ownership the same way.
     */
    private function bindUser(): void
    {
        Route::bind('user', function ($value) {
            $ctx = app(TenantContext::class);
            $this->primeContext($ctx);

            $user = User::find($value);
            if (! $user) {
                throw new NotFoundHttpException();
            }

            if ($ctx->has() && (int) $user->tenant_id !== (int) $ctx->id()) {
                throw new NotFoundHttpException(); // another tenant -> not found
            }

            return $user;
        });
    }
}
