<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Like BelongsToTenant, but WITHOUT the global tenant scope.
 *
 * The authenticatable User model is a special case: Laravel's session guard
 * re-fetches the user (User::find) on EVERY request, during auth resolution —
 * which happens before the ResolveTenant middleware sets tenant context. If User
 * carried the fail-closed global scope, that fetch would return null (no context
 * yet) → the guard treats you as logged out → redirect-to-login LOOP.
 *
 * So User stays globally UNSCOPED (auth can always load it), and any place that
 * LISTS users must filter by tenant explicitly (see User::scopeForCurrentTenant
 * and UserController@index). This is the standard multi-tenant + auth pattern.
 *
 * This trait still provides the tenant() relation and auto-fills tenant_id on
 * create, so new users are correctly stamped.
 */
trait BelongsToTenantUnscoped
{
    public static function bootBelongsToTenantUnscoped(): void
    {
        static::creating(function ($model) {
            if ($model->tenant_id === null) {
                $ctx = app(TenantContext::class);
                if ($ctx->has()) {
                    $model->tenant_id = $ctx->id();
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Explicit tenant filter for LIST queries (the scope isn't automatic here).
     * Respects platform-admin bypass. Use: User::forCurrentTenant()->paginate().
     */
    public function scopeForCurrentTenant($query)
    {
        $ctx = app(TenantContext::class);

        if ($ctx->isBypassed()) {
            return $query; // platform admin: all users
        }
        if ($ctx->has()) {
            return $query->where($this->getTable() . '.tenant_id', $ctx->id());
        }
        // No context on a list query → show nothing (fail closed for lists).
        return $query->whereRaw('1 = 0');
    }
}
