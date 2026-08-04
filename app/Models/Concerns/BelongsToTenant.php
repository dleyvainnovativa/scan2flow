<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Apply to any tenant-owned model. Adds the global TenantScope and auto-fills
 * tenant_id on create from the current TenantContext.
 */

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            // Only auto-fill if not already set (jobs/seeders may set it
            // explicitly) and a tenant is in context.
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
}
