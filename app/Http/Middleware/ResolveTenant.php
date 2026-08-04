<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current tenant from the authenticated user and sets it on the
 * shared TenantContext, so the global scope constrains everything for the rest
 * of the request. Runs AFTER auth (so $request->user() is available).
 *
 * Platform super-admins (users with no tenant_id, operating above tenancy) are
 * left unresolved here; their controllers use TenantContext::withoutScope() or
 * set a specific tenant explicitly.
 */
class ResolveTenant
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->tenant_id !== null) {
            $this->context->set($user->tenant_id);

            // Optional hard stop: block suspended tenants early.
            $tenant = $user->tenant; // relation
            if ($tenant && ! $tenant->isActive()) {
                abort(403, 'Esta cuenta está suspendida. Contacta al administrador.');
            }
        }

        return $next($request);
    }
}
