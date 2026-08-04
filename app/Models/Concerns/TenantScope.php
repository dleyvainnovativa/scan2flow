<?php

namespace App\Models\Concerns;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Automatically constrains every query on a tenant-owned model to the current
 * tenant. Cross-tenant leakage becomes impossible by default; the only way past
 * it is TenantContext::withoutScope() (platform/super-admin operations).
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $ctx = app(TenantContext::class);

        // Platform/super-admin path: no constraint.
        if ($ctx->isBypassed()) {
            return;
        }

        // If a tenant is resolved, constrain to it. If none is resolved (e.g. an
        // unauthenticated context), constrain to an impossible id so nothing
        // leaks rather than everything being exposed. Fail closed, not open.
        if ($ctx->has()) {
            $builder->where($model->getTable() . '.tenant_id', $ctx->id());
        } else {
            $builder->whereRaw('1 = 0');
        }
    }
}
