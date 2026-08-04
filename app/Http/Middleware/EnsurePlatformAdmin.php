<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the platform (super-admin) area. Distinct from EnsureUserIsAdmin, which
 * is the TENANT admin. A platform admin runs the SaaS itself.
 *
 * Platform admins operate above tenancy (tenant_id = null), so their controllers
 * use TenantContext::withoutScope() to read across tenants.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && $user->is_platform_admin, 403, 'Acceso restringido a operadores de la plataforma.');

        return $next($request);
    }
}
